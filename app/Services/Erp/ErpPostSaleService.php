<?php

namespace App\Services\Erp;

use App\Models\ErpCredential;
use App\Models\ErpIdMapping;
use App\Models\ErpPostSaleSync;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ErpPostSaleService
{
    // billing_type: 1 = Fixed Rate, 2 = Project Hours, 3 = Task Hours, 4 = Project Rate per Hour
    // status:       1 = Not Started (typical Perfex default)
    protected const PROJECT_BILLING_TYPE = 1;
    protected const PROJECT_STATUS = 1;
    protected const PROJECT_CUSTOM_FIELD_LABELS = [
        'invoice_number' => 'Invoice Number',
        'product_title' => 'Product/Product Variant',
        'purchase_date' => 'Purchase Date',
        'lms_order_id' => 'LMS Order ID',
    ];

    public function syncSale(Sale $sale, OrderItem $orderItem): ?ErpPostSaleSync
    {
        $product = $orderItem->product ?: optional($orderItem->productOrder)->product;

        if (!$product instanceof Product || !$product->erp_post_sale_enabled) {
            return null;
        }

        $order = $orderItem->order ?: $sale->order;
        $buyer = $orderItem->user ?: $sale->buyer ?: optional($order)->user;
        $vendorId = (int) ($product->creator_id ?: $sale->seller_id);

        if (empty($order) || empty($buyer) || empty($vendorId)) {
            return null;
        }

        $invoiceNumber = $this->invoiceNumber($order->id);
        $payload = $this->buildPayload($sale, $orderItem, $product, $invoiceNumber);

        $sync = ErpPostSaleSync::firstOrCreate([
            'vendor_id' => $vendorId,
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'product_id' => $product->id,
        ], [
            'sale_id' => $sale->id,
            'status' => 'pending',
            'request_payload' => $payload,
        ]);

        if ($sync->status === 'success') {
            return $sync;
        }

        $credential = ErpCredential::where('vendor_id', $vendorId)
            ->where('type', 'import_export')
            ->where('is_active', true)
            ->first();

        if (empty($credential) || empty($credential->base_url) || empty($credential->api_key)) {
            return $this->markFailed($sync, $payload, 'Active Perfex ERP credential is not configured for this seller.');
        }

        // RETRY IDEMPOTENCY: pichhli attempt ka poora state (project id, per-task result,
        // custom-fields applied flag) reset se PEHLE capture karo. Neeche $sync->update()
        // sirf status/attempts/request_payload badalta hai, response_payload ko touch nahi
        // karta, lekin hum explicit copy rakhte hain taake koi confusion na ho.
        $previousStatus = (string) $sync->status;
        $previousResponse = is_array($sync->response_payload) ? $sync->response_payload : [];
        $existingRemoteProjectId = $sync->remote_project_id;

        $sync->update([
            'sale_id' => $sale->id,
            'status' => 'pending',
            'attempts' => $sync->attempts + 1,
            'request_payload' => $payload,
            'last_attempted_at' => now(),
            'error_message' => null,
        ]);

        $client = $this->makeClient($credential);

        if (!empty($existingRemoteProjectId)) {
            // ---------------------------------------------------------------
            // RETRY PATH: Project pehle se bana hua hai -> createProject() nahi.
            // ---------------------------------------------------------------
            $remoteProjectId = (string) $existingRemoteProjectId;
            $projectBody = $previousResponse['project'] ?? [];
            $taskState = $this->restoreTaskState($previousResponse, $previousStatus, $payload['tasks']);

            // Custom fields: agar pehle apply nahi hue (partial) to dobara try karo.
            // Purane rows (jin me flag store nahi hai) me: previous status 'partial'
            // ka matlab hi yeh tha ke custom fields fail hue the.
            $customFieldsApplied = array_key_exists('custom_fields_applied', $previousResponse)
                ? (bool) $previousResponse['custom_fields_applied']
                : ($previousStatus !== 'partial');

            if (!$customFieldsApplied) {
                $customFieldsApplied = $this->restoreCustomFields(
                    $client,
                    $vendorId,
                    $buyer,
                    $payload,
                    $remoteProjectId,
                    $order->id,
                    $invoiceNumber
                );
            }
        } else {
            // ---------------------------------------------------------------
            // FIRST-TIME PATH
            // ---------------------------------------------------------------

            // Step 1: buyer ka Perfex clientid (auto-create agar missing ho).
            $clientId = $this->resolveErpClientId($client, $vendorId, $buyer);

            if (empty($clientId)) {
                return $this->markFailed(
                    $sync,
                    $payload,
                    'No Perfex client id found for this buyer (checked users.erp_client_id, erp_id_mappings, and a fresh customer lookup by company). Link the buyer to a Perfex customer first.'
                );
            }

            // Step 2: Project create (mandatory fields + project_cost = order amount).
            $projectPayload = $this->buildProjectPayload($payload, $clientId);

            // Custom fields fetch alag se: fail ho to project create hona nahi rukta,
            // sync "partial" mark hota hai. Payment is method se kabhi touch nahi hoti.
            $customFieldsFetch = $client->getCustomFields('projects');
            $customFieldsFetchFailed = empty($customFieldsFetch['success']);

            if ($customFieldsFetchFailed) {
                Log::warning('ERP: Perfex project custom fields fetch failed; project will be created without custom fields.', [
                    'vendor_id' => $vendorId,
                    'order_id' => $order->id,
                    'invoice_number' => $invoiceNumber,
                    'response' => $customFieldsFetch,
                ]);
            }

            $customFields = $customFieldsFetchFailed
                ? []
                : $this->mapCustomFieldIds($customFieldsFetch['body'] ?? [], $payload);

            if (!empty($customFields)) {
                $projectPayload['custom_fields'] = [
                    'projects' => $customFields,
                ];
            }

            $projectResult = $client->createProject($projectPayload);

            if (empty($projectResult['success'])) {
                return $this->markFailed(
                    $sync,
                    $payload,
                    $projectResult['error'] ?? ('Perfex HTTP ' . ($projectResult['status'] ?? 0)),
                    $projectResult['body'] ?? []
                );
            }

            $projectBody = $projectResult['body'] ?? [];
            $remoteProjectId = Arr::get($projectBody, 'project_id')
                ?: Arr::get($projectBody, 'id')
                ?: Arr::get($projectBody, 'data.project_id')
                ?: Arr::get($projectBody, 'data.id');

            if (empty($remoteProjectId)) {
                // createProject() ke success response me id nahi hoti; naam (jisme invoice
                // number hai) + clientid se list me se dhoondo.
                $remoteProjectId = $this->findNewestProjectId($client, $payload['project_name'], $clientId);

                if (empty($remoteProjectId)) {
                    return $this->markFailed($sync, $payload, 'Perfex reported the project was created but it could not be found afterwards by name+clientid.', $projectBody);
                }
            }

            $remoteProjectId = (string) $remoteProjectId;
            $taskState = [];
            $customFieldsApplied = !$customFieldsFetchFailed;
        }

        // Project id + mapping FORAN persist karo (tasks se pehle). Agar task loop ke
        // beech process crash / exception ho jaye tab bhi retry isi project ko reuse karega.
        $this->storeMapping($vendorId, 'order', $order->id, $remoteProjectId);
        $this->persistProgress($sync, $remoteProjectId, $projectBody, $taskState, $customFieldsApplied);

        // Step 3: Tasks — IDEMPOTENT. Sirf woh tasks banao jo pehle successfully nahi bane.
        $taskKeys = $this->taskKeys($payload['tasks']);

        foreach ($payload['tasks'] as $index => $task) {
            $key = $taskKeys[$index];

            if (!empty($taskState[$key]['success'])) {
                // Pehle hi ban chuka hai — dobara createTask() nahi.
                continue;
            }

            $taskPayload = [
                // mandatory
                'name' => $task['title'],
                'startdate' => $payload['purchase_date'],
                'rel_type' => 'project',
                'rel_id' => $remoteProjectId,
            ];

            // optional fields — sirf tab jab real data ho
            if (!empty($task['assigned'])) {
                $taskPayload['assignees'] = $task['assigned'];
            }

            if (!empty($payload['due_date'])) {
                $taskPayload['duedate'] = $payload['due_date'];
            }

            if (!empty($payload['description'])) {
                $taskPayload['description'] = $payload['description'];
            }

            $taskResult = $client->createTask($taskPayload);
            $taskOk = !empty($taskResult['success']);

            $taskState[$key] = [
                'title' => $task['title'],
                'success' => $taskOk,
                'body' => $taskResult['body'] ?? null,
            ];

            // Har task ke baad progress save — crash ki surat me bhi is task ka result yaad rahe.
            $this->persistProgress($sync, $remoteProjectId, $projectBody, $taskState, $customFieldsApplied);

            if (!$taskOk) {
                Log::error('ERP: task creation failed for a post-sale project.', [
                    'vendor_id' => $vendorId,
                    'order_id' => $order->id,
                    'invoice_number' => $invoiceNumber,
                    'remote_project_id' => $remoteProjectId,
                    'task_title' => $task['title'],
                    'response' => $taskResult,
                ]);
            }
        }

        // Overall status: success sirf tab jab project AND saare tasks AND custom fields
        // teeno theek hon. Custom fields apply na hon => "partial" (retry me dobara try hoga).
        $allTasksOk = true;
        foreach ($taskKeys as $key) {
            if (empty($taskState[$key]['success'])) {
                $allTasksOk = false;
                break;
            }
        }

        $erpIssues = [];

        if (!$customFieldsApplied) {
            $erpIssues[] = 'Perfex custom fields could not be applied to the project; project exists without custom field data.';
        }

        if (!$allTasksOk) {
            $erpIssues[] = 'Project created, but one or more tasks failed to create. Check logs.';
        }

        $finalStatus = 'success';
        if (!$allTasksOk) {
            $finalStatus = 'failed';
        } elseif (!$customFieldsApplied) {
            $finalStatus = 'partial';
        }

        $sync->update([
            'status' => $finalStatus,
            'remote_project_id' => $remoteProjectId,
            'response_payload' => $this->responsePayload($projectBody, $taskState, $customFieldsApplied),
            'error_message' => $erpIssues ? implode(' ', $erpIssues) : null,
        ]);

        if ($finalStatus !== 'success') {
            Log::warning('ERP post-sale sync completed with issues.', [
                'vendor_id' => $vendorId,
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'status' => $finalStatus,
                'issues' => $erpIssues,
            ]);
        }

        return $sync;
    }

    /**
     * Thin factory wrapper around `new ErpClient(...)`, tests me mock karne ke liye.
     */
    protected function makeClient(ErpCredential $credential): ErpClient
    {
        return new ErpClient($credential);
    }

    /**
     * Project create/update ka common payload (create aur retry-update dono use karte hain).
     */
    protected function buildProjectPayload(array $payload, string $clientId): array
    {
        return [
            'name' => $payload['project_name'],
            'rel_type' => 'customer',
            'clientid' => $clientId,
            'billing_type' => self::PROJECT_BILLING_TYPE,
            'start_date' => $payload['purchase_date'],
            'status' => self::PROJECT_STATUS,
            'description' => $this->projectDescription($payload),
            'project_cost' => $payload['amount'],
        ];
    }

    /**
     * Retry par: pehle se bane hue Project par custom fields dobara apply karne ki koshish.
     *
     * Returns true SIRF tab jab custom fields waqai apply ho gaye (ya apply karne ko kuch
     * tha hi nahi). Har doosri surat me false => sync "partial" hi rahega, kabhi
     * galat tareeqe se "success" nahi banega.
     *
     * NOTE: ErpClient me updateProject($projectId, array $payload) hona chahiye (Perfex
     * PUT /api/projects/{id}). Agar method maujood nahi to hum false return karte hain
     * (status partial rehta hai) — silently success nahi banate.
     */
    protected function restoreCustomFields(
        ErpClient $client,
        int $vendorId,
        $buyer,
        array $payload,
        string $remoteProjectId,
        int $orderId,
        string $invoiceNumber
    ): bool {
        $logContext = [
            'vendor_id' => $vendorId,
            'order_id' => $orderId,
            'invoice_number' => $invoiceNumber,
            'remote_project_id' => $remoteProjectId,
        ];

        $customFieldsFetch = $client->getCustomFields('projects');

        if (empty($customFieldsFetch['success'])) {
            Log::warning('ERP: retry — custom fields fetch failed again; sync stays partial.', $logContext + [
                'response' => $customFieldsFetch,
            ]);

            return false;
        }

        $customFields = $this->mapCustomFieldIds($customFieldsFetch['body'] ?? [], $payload);

        if (empty($customFields)) {
            // Perfex me matching custom field definitions hi nahi — apply karne ko kuch nahi.
            return true;
        }

        if (!method_exists($client, 'updateProject')) {
            Log::warning('ERP: retry — ErpClient::updateProject() is not available; custom fields cannot be restored, sync stays partial.', $logContext);

            return false;
        }

        $clientId = $this->resolveErpClientId($client, $vendorId, $buyer);

        if (empty($clientId)) {
            Log::warning('ERP: retry — could not resolve Perfex client id to update project custom fields; sync stays partial.', $logContext);

            return false;
        }

        $projectPayload = $this->buildProjectPayload($payload, $clientId);
        $projectPayload['custom_fields'] = ['projects' => $customFields];

        $result = $client->updateProject($remoteProjectId, $projectPayload);

        if (empty($result['success'])) {
            Log::warning('ERP: retry — project custom fields update failed; sync stays partial.', $logContext + [
                'response' => $result,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Har task ke liye stable key. Key title par based hai (index par nahi) taake templates
     * ka order badle to bhi mapping sahi rahe. Same title do baar ho to "#2", "#3" suffix.
     */
    protected function taskKeys(array $tasks): array
    {
        $seen = [];
        $keys = [];

        foreach ($tasks as $index => $task) {
            $base = sha1(mb_strtolower(trim((string) ($task['title'] ?? ''))));
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $keys[$index] = $seen[$base] > 1 ? $base . '#' . $seen[$base] : $base;
        }

        return $keys;
    }

    /**
     * Pichhli attempt ka per-task state wapas banao.
     *  - Naye format (task_state) me seedha wahi use hota hai.
     *  - Purana row jo "partial" tha: partial tab hi hota tha jab SAARE tasks ban chuke the,
     *    isliye sab ko done mark kar dete hain (warna duplicate ban jate).
     *  - Purana row jo "failed" tha aur task_state nahi hai: kaun sa task bana kaun sa nahi,
     *    yeh data me nahi hai — is case ko manually Perfex me verify karna hoga.
     */
    protected function restoreTaskState(array $previousResponse, string $previousStatus, array $tasks): array
    {
        if (isset($previousResponse['task_state']) && is_array($previousResponse['task_state'])) {
            return $previousResponse['task_state'];
        }

        if ($previousStatus === 'partial') {
            $state = [];

            foreach ($this->taskKeys($tasks) as $index => $key) {
                $state[$key] = [
                    'title' => $tasks[$index]['title'] ?? '',
                    'success' => true,
                    'body' => null,
                ];
            }

            return $state;
        }

        return [];
    }

    protected function responsePayload(array $projectBody, array $taskState, bool $customFieldsApplied): array
    {
        return [
            'project' => $projectBody,
            'custom_fields_applied' => $customFieldsApplied,
            'task_state' => $taskState,
            // backward-compat: purana format (bodies ki list)
            'tasks' => array_values(array_map(fn ($t) => $t['body'] ?? null, $taskState)),
        ];
    }

    protected function persistProgress(
        ErpPostSaleSync $sync,
        string $remoteProjectId,
        array $projectBody,
        array $taskState,
        bool $customFieldsApplied
    ): void {
        $sync->update([
            'remote_project_id' => $remoteProjectId,
            'response_payload' => $this->responsePayload($projectBody, $taskState, $customFieldsApplied),
        ]);
    }

    /**
     * Resolve the buyer's Perfex client id (clientid).
     * Order of lookup:
     *  1. users.erp_client_id column
     *  2. erp_id_mappings (entity_type = 'customer', local_id = buyer id, vendor_id = seller id)
     *  3. Existing Perfex contact email.
     *  4. Auto-create via pushClient(), phir list se company match karke userid nikalna
     *     (create response me id nahi aati).
     */
    protected function resolveErpClientId(ErpClient $client, int $vendorId, $buyer): ?string
    {
        if (!empty($buyer->erp_client_id)) {
            return (string) $buyer->erp_client_id;
        }

        $mapping = ErpIdMapping::where('vendor_id', $vendorId)
            ->where('entity_type', 'customer')
            ->where('local_id', $buyer->id)
            ->first();

        if (!empty($mapping) && !empty($mapping->remote_id)) {
            return $mapping->remote_id;
        }

        $remoteClientId = $this->findClientIdByContactEmail($client, (string) $buyer->email);

        if (!empty($remoteClientId)) {
            $this->storeMapping($vendorId, 'customer', $buyer->id, (string) $remoteClientId);

            return (string) $remoteClientId;
        }

        // No mapping/email match anywhere — create the customer in Perfex now.
        $company = $buyer->full_name ?: $buyer->email;

        $customerPayload = array_filter([
            'company' => $company,
            'email' => $buyer->email,
            'phone' => $buyer->phone ?? null,
        ]);

        $result = $client->pushClient($customerPayload);

        if (empty($result['success'])) {
            Log::error('ERP: customer create (pushClient) failed.', [
                'vendor_id' => $vendorId,
                'buyer_id' => $buyer->id,
                'response' => $result,
            ]);

            return null;
        }

        $remoteClientId = $this->findClientIdByContactEmail($client, (string) $buyer->email);

        if (!empty($remoteClientId)) {
            $this->storeMapping($vendorId, 'customer', $buyer->id, (string) $remoteClientId);

            return (string) $remoteClientId;
        }

        // The create endpoint may not return an id — look the record up by company instead.
        $remoteClientId = $this->findNewestClientIdByCompany($client, $company);

        if (empty($remoteClientId)) {
            Log::error('ERP: customer was created but could not be found afterwards by company match.', [
                'vendor_id' => $vendorId,
                'buyer_id' => $buyer->id,
                'company' => $company,
            ]);

            return null;
        }

        $this->storeMapping($vendorId, 'customer', $buyer->id, (string) $remoteClientId);

        return (string) $remoteClientId;
    }

    protected function findClientIdByContactEmail(ErpClient $client, ?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        $result = $client->searchContacts($email);

        if (empty($result['success'])) {
            return null;
        }

        $rows = $result['body'] ?? [];
        if (isset($rows['email'])) {
            $rows = [$rows];
        }

        $match = collect($rows)
            ->filter(fn ($row) => strcasecmp(trim((string) ($row['email'] ?? '')), $email) === 0)
            ->sortByDesc(fn ($row) => $row['datecreated'] ?? '')
            ->first();

        if (empty($match) || empty($match['userid'])) {
            return null;
        }

        return (string) $match['userid'];
    }

    /**
     * Full customer list me se "company" exact match wali sabse nayi row dhoondta hai.
     * Best-effort workaround hai (company unique key nahi), kyunke create response me id nahi.
     */
    protected function findNewestClientIdByCompany(ErpClient $client, string $company): ?string
    {
        $result = $client->getCustomers();

        if (empty($result['success'])) {
            return null;
        }

        $match = collect($result['body'] ?? [])
            ->filter(fn ($row) => isset($row['company']) && trim((string) $row['company']) === trim($company))
            ->sortByDesc(fn ($row) => $row['datecreated'] ?? '')
            ->first();

        if (empty($match) || empty($match['userid'])) {
            return null;
        }

        return (string) $match['userid'];
    }

    /**
     * Full project list me se exact project name + clientid match dhoondta hai
     * (createProject() ke response me id nahi hoti). Naam me invoice number hai, isliye
     * effectively unique. Multiple match par sabse bada id.
     */
    protected function findNewestProjectId(ErpClient $client, string $projectName, string $clientId): ?string
    {
        $result = $client->getProjects();

        if (empty($result['success'])) {
            return null;
        }

        $match = collect($result['body'] ?? [])
            ->filter(fn ($row) => ($row['name'] ?? null) === $projectName && (string) ($row['clientid'] ?? '') === $clientId)
            ->sortByDesc(fn ($row) => (int) ($row['id'] ?? 0))
            ->first();

        if (empty($match) || empty($match['id'])) {
            return null;
        }

        return (string) $match['id'];
    }

    protected function storeMapping(int $vendorId, string $entityType, int $localId, string $remoteId): void
    {
        ErpIdMapping::updateOrCreate(
            ['vendor_id' => $vendorId, 'entity_type' => $entityType, 'local_id' => $localId],
            ['remote_id' => $remoteId, 'last_synced_at' => now()]
        );
    }

    public function buildPayload(Sale $sale, OrderItem $orderItem, Product $product, string $invoiceNumber): array
    {
        $order = $orderItem->order ?: $sale->order;
        $buyer = $orderItem->user ?: $sale->buyer ?: optional($order)->user;
        $category = $product->erp_category_name ?: optional($product->category)->title;
        $subcategory = $product->erp_subcategory_name ?: '';
        $invoiceTitle = collect([$category ?: 'Rocket LMS', $subcategory])->filter()->implode(' / ');
        $projectName = $invoiceTitle . ' - ' . $invoiceNumber . ' - ' . $product->title;

        // variant sirf tab set hoti hai jab koi real specification data ho.
        $variantLabel = $this->productVariant($orderItem, $product);

        return [
            'invoice_number' => $invoiceNumber,
            'lms_order_id' => optional($order)->id,
            'product_id' => $product->id,
            'product_variant' => $variantLabel, // null hoga jab variant na ho
            'purchase_date' => date('Y-m-d', $sale->created_at ?: time()),
            'project_name' => $projectName,
            'product_title' => $product->title,
            'category' => $category ?: 'Rocket LMS',
            'subcategory' => $subcategory,
            'amount' => (float) ($orderItem->total_amount ?? $sale->total_amount ?? 0),
            'description' => trim(strip_tags($product->summary ?: $product->description ?: '')),
            'customer_email' => optional($buyer)->email,
            'customer_name' => optional($buyer)->full_name,
            'due_date' => $this->dueDate($sale, $product),
            'staff_ids' => $this->staffIds($product)->all(),
            'tasks' => $this->buildTasks($product, $variantLabel),
        ];
    }

    protected function dueDate(Sale $sale, Product $product): ?string
    {
        $days = $product->delivery_estimated_time ?? null;

        if (empty($days) || !is_numeric($days)) {
            return null;
        }

        $purchaseTimestamp = $sale->created_at ?: time();
        $base = is_numeric($purchaseTimestamp) ? (int) $purchaseTimestamp : strtotime((string) $purchaseTimestamp);

        return date('Y-m-d', strtotime('+' . (int) $days . ' days', $base));
    }

    /**
     * Already-fetched custom field definitions ko payload values se map karta hai.
     * Khud koi HTTP call nahi karta.
     */
    protected function mapCustomFieldIds(array $fields, array $payload): array
    {
        $fieldsByLabel = collect($fields)
            ->mapWithKeys(function ($field) {
                $label = trim((string) ($field['label'] ?? ''));
                $id = $field['custom_field_id'] ?? null;

                return ($label !== '' && !empty($id)) ? [mb_strtolower($label) => $id] : [];
            });

        $values = $this->projectCustomFieldValues($payload);
        $customFields = [];

        foreach (self::PROJECT_CUSTOM_FIELD_LABELS as $key => $label) {
            $fieldId = $fieldsByLabel->get(mb_strtolower($label));

            if (!empty($fieldId) && isset($values[$key])) {
                $customFields[$fieldId] = $values[$key];
            }
        }

        return $customFields;
    }

    protected function projectDescription(array $payload): string
    {
        $details = collect($this->projectCustomFieldValues($payload))
            ->map(fn ($value, $key) => self::PROJECT_CUSTOM_FIELD_LABELS[$key] . ': ' . $value)
            ->implode("\n");

        return trim(implode("\n\n", array_filter([$payload['description'] ?? null, $details])));
    }

    /**
     * "Product/Product Variant" field: variant maujood ho to variant, warna product id.
     */
    protected function projectCustomFieldValues(array $payload): array
    {
        $productLabel = !empty($payload['product_variant'])
            ? $payload['product_variant']
            : (string) ($payload['product_id'] ?? '');

        return [
            'invoice_number' => $payload['invoice_number'] ?? '',
            'product_title' => $productLabel,
            'purchase_date' => $payload['purchase_date'] ?? '',
            'lms_order_id' => (string) ($payload['lms_order_id'] ?? ''),
        ];
    }

    /**
     * Real variant/specification na ho to null.
     */
    protected function productVariant(OrderItem $orderItem, Product $product): ?string
    {
        $specifications = optional($orderItem->productOrder)->specifications;

        if (empty($specifications)) {
            return null;
        }

        $decoded = is_array($specifications) ? $specifications : json_decode((string) $specifications, true);

        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        $variant = collect($decoded)
            ->map(function ($value, $key) {
                if (is_array($value)) {
                    $value = implode(', ', array_filter($value));
                }

                return trim((is_string($key) ? $key . ': ' : '') . (string) $value);
            })
            ->filter()
            ->implode(' / ');

        return $variant !== '' ? $variant : null;
    }

    /**
     * Koi round-robin nahi — har task me SAARE selected staff assign hote hain.
     * Task templates na hon to default task title me variant ka naam (agar ho).
     */
    protected function buildTasks(Product $product, ?string $variantLabel = null): array
    {
        $staffIds = $this->staffIds($product);

        $templateTitles = collect($product->erp_task_templates ?: [])
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->values();

        if ($templateTitles->isEmpty()) {
            $defaultTitle = $variantLabel
                ? trim($product->title . ' - ' . $variantLabel)
                : $product->title;

            $templateTitles = collect([$defaultTitle]);
        }

        if ($staffIds->isEmpty()) {
            return $templateTitles->map(fn ($title) => ['title' => $title, 'assigned' => []])->all();
        }

        return $templateTitles
            ->map(fn ($title) => ['title' => $title, 'assigned' => $staffIds->all()])
            ->all();
    }

    protected function staffIds(Product $product)
    {
        return collect($product->erp_staff_ids ?: [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function invoiceNumber(int $orderId): string
    {
        return 'INV-' . $orderId;
    }

    /**
     * Har early-failure path yahan se guzarta hai. Payment status kabhi touch nahi hota —
     * sirf ErpPostSaleSync row update hoti hai.
     */
    protected function markFailed(ErpPostSaleSync $sync, array $payload, string $message, array $response = []): ErpPostSaleSync
    {
        Log::error('ERP post-sale sync failed.', [
            'vendor_id' => $sync->vendor_id,
            'order_id' => $sync->order_id,
            'invoice_number' => $sync->invoice_number,
            'message' => $message,
            'response' => $response,
        ]);

        $sync->update([
            'status' => 'failed',
            'request_payload' => $payload,
            'response_payload' => $response,
            'error_message' => $message,
            'last_attempted_at' => now(),
        ]);

        return $sync;
    }
}