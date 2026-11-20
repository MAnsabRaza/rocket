<?php

namespace App\Services\Erp;

use App\Models\ErpCredential;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perfex ERP ke sath low-level HTTP communication.
 * Har call ek vendor ke ErpCredential (type=import_export) ke base_url + api_key use karti hai.
 *
 * CONFIRMED via live curl testing (2026-08-31):
 *  - Customer create/list endpoint is /_ERP/api/customers (NOT /api/v1/clients)
 *  - Body must be form-urlencoded (NOT JSON)
 *  - Create response is {"status":true,"message":"..."} — NO id is returned.
 *    Caller must look the record up afterwards (see ErpPostSaleService::findNewestClientIdByCompany).
 *  - Client rows do not carry email; customer email belongs to contacts.
 *  - Query string filters (e.g. ?email=...) appear to be ignored server-side — the endpoint
 *    always returns the full customer list. Do not rely on server-side filtering.
 *
 */
class ErpClient
{
    protected ErpCredential $credential;

    public function __construct(ErpCredential $credential)
    {
        $this->credential = $credential;
    }

    protected function http()
    {
        return Http::withHeaders([
                'authtoken' => $this->credential->api_key,
            ])
            ->baseUrl(rtrim($this->credential->base_url, '/'))
            ->timeout(15)
            ->retry(2, 300); // network-level retry, queue job retry alag se (sync-level)
    }

    /**
     * Client (Customer) create/update — Rocket customer → Perfex client.
     * NOTE: create response does not include an id — caller must look it up
     * (e.g. via getCustomers() + company match) after a successful create.
     */
    public function pushClient(array $payload, ?string $remoteId = null)
    {
        return $this->call($remoteId ? 'PUT' : 'POST', '/_ERP/api/customers' . ($remoteId ? "/{$remoteId}" : ''), $payload);
    }

    /**
     * List/search customers. Query params are NOT reliably honored server-side
     * (confirmed: ?email=... still returned unrelated rows) — always fetch and
     * filter client-side.
     */
    public function getCustomers(array $query = [])
    {
        return $this->call('GET', '/_ERP/api/customers', $query);
    }

    public function searchContacts(string $keyword)
    {
        return $this->call('GET', '/_ERP/api/contacts/search/' . rawurlencode($keyword));
    }

    /**
     * List projects. CONFIRMED fields on each row: id, name, clientid, project_created
     * (date only, no time), plus a nested client_data. Same as customers — createProject()
     * doesn't return an id, so after a successful create we list and match by name+clientid.
     */
    public function getProjects(array $query = [])
    {
        return $this->call('GET', '/_ERP/api/projects', $query);
    }

    public function getCustomFields(string $type, ?string $remoteId = null)
    {
        return $this->call('GET', '/_ERP/api/custom_fields/' . rawurlencode($type) . ($remoteId ? '/' . rawurlencode($remoteId) : ''));
    }

    public function pushItem(array $payload, ?string $remoteId = null)
    {
        return $this->call($remoteId ? 'PUT' : 'POST', '/api/v1/items' . ($remoteId ? "/{$remoteId}" : ''), $payload);
    }

    public function pushInvoice(array $payload, ?string $remoteId = null)
    {
        return $this->call($remoteId ? 'PUT' : 'POST', '/api/v1/invoices' . ($remoteId ? "/{$remoteId}" : ''), $payload);
    }

    public function pushAppointment(array $payload, ?string $remoteId = null)
    {
        return $this->call($remoteId ? 'PUT' : 'POST', '/api/v1/appointments' . ($remoteId ? "/{$remoteId}" : ''), $payload);
    }

    public function pushPayment(array $payload, ?string $remoteId = null)
    {
        return $this->call($remoteId ? 'PUT' : 'POST', '/api/v1/payments' . ($remoteId ? "/{$remoteId}" : ''), $payload);
    }

    public function getPostSaleCategories()
    {
        return $this->call('GET', '/api/post_sale_categories');
    }

    public function getStaff()
    {
        return $this->call('GET', '/_ERP/api/staffs');
    }

    /**
     * @deprecated Replaced by createProject() + createTask() below (matches
     * Perfex's core /api/projects + /api/tasks endpoints). Kept here in case
     * anything else still calls it — remove once confirmed unused.
     */
    public function createProjectFromOrder(array $payload)
    {
        return $this->call('POST', '/_ERP/api/create_project_from_order', $payload);
    }

    /**
     * Create a Perfex Project. Mandatory payload keys per Perfex docs:
     * name, rel_type, clientid, billing_type, start_date, status.
     *
     * NOTE: path now uses the /_ERP prefix, matching the confirmed customers/staffs
     * pattern. This specific endpoint has NOT been curl-confirmed yet — test it
     * (POST with a real clientid) before trusting this in production.
     */
    public function createProject(array $payload)
    {
        return $this->call('POST', '/_ERP/api/projects', $payload);
    }

    /**
     * Create a Perfex Task under a project. Mandatory payload keys per Perfex docs:
     * name, startdate, rel_type, rel_id. Optional: assignees, duedate, description, etc.
     *
     * NOTE: path now uses the /_ERP prefix — same caveat as createProject() above.
     */
    public function createTask(array $payload)
    {
        return $this->call('POST', '/_ERP/api/tasks', $payload);
    }

    /**
     * Flow 2: supplier feed pull — dusre supplier ka inventory
     */
    public function pullSupplierFeed(array $query = [])
    {
        return $this->call('GET', '/api/v1/supplier-feed', $query);
    }

    protected function call(string $method, string $uri, array $payload = [])
    {
        try {
            $request = $this->http();

            $response = match (strtoupper($method)) {
                'GET'    => $request->get($uri, $payload),
                // Confirmed: this API expects form-urlencoded bodies, not JSON.
                'PUT'    => $request->asForm()->put($uri, $payload),
                'DELETE' => $request->delete($uri, $payload),
                default  => $request->asForm()->post($uri, $payload),
            };

            $json = $response->json();

            // This API can return HTTP 200 with an internal {"status":false,...}
            // for business-level failures, so HTTP-success alone isn't enough.
            $bodyStatus = is_array($json) ? Arr::get($json, 'status') : null;
            $success = $response->successful() && $bodyStatus !== false;

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $json ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('ERP HTTP call threw an exception.', [
                'method' => $method,
                'uri'    => $uri,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => 0,
                'body'    => [],
                'error'   => $e->getMessage(),
            ];
        }
    }
}