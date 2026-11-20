<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    |
    */

    'accepted' => 'Το πεδίο :attribute πρέπει να γίνει αποδεκτό.',
    'active_url' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη διεύθυνση URL.',
    'after' => 'Το πεδίο :attribute πρέπει να είναι ημερομηνία μετά την :date.',
    'after_or_equal' => 'Το πεδίο :attribute πρέπει να είναι ημερομηνία μετά ή ίση με την :date.',
    'alpha' => 'Το πεδίο :attribute μπορεί να περιέχει μόνο γράμματα.',
    'alpha_dash' => 'Το πεδίο :attribute μπορεί να περιέχει μόνο γράμματα, αριθμούς, παύλες και κάτω παύλες.',
    'alpha_num' => 'Το πεδίο :attribute μπορεί να περιέχει μόνο γράμματα και αριθμούς.',
    'array' => 'Το πεδίο :attribute πρέπει να είναι πίνακας.',
    'before' => 'Το πεδίο :attribute πρέπει να είναι ημερομηνία πριν από την :date.',
    'before_or_equal' => 'Το πεδίο :attribute πρέπει να είναι ημερομηνία πριν ή ίση με την :date.',
    'between' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι μεταξύ :min και :max.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι μεταξύ :min και :max kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει από :min έως :max χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να έχει από :min έως :max στοιχεία.',
    ],
    'boolean' => 'Το πεδίο :attribute πρέπει να είναι true ή false.',
    'confirmed' => 'Η επιβεβαίωση του πεδίου :attribute δεν ταιριάζει.',
    'date' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη ημερομηνία.',
    'date_equals' => 'Το πεδίο :attribute πρέπει να είναι ημερομηνία ίση με την :date.',
    'date_format' => 'Το πεδίο :attribute πρέπει να ταιριάζει με τη μορφή :format.',
    'different' => 'Τα πεδία :attribute και :other πρέπει να είναι διαφορετικά.',
    'digits' => 'Το πεδίο :attribute πρέπει να αποτελείται από :digits ψηφία.',
    'digits_between' => 'Το πεδίο :attribute πρέπει να αποτελείται από :min έως :max ψηφία.',
    'dimensions' => 'Το πεδίο :attribute έχει μη έγκυρες διαστάσεις εικόνας.',
    'distinct' => 'Το πεδίο :attribute περιέχει διπλότυπη τιμή.',
    'email' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη διεύθυνση email.',
    'ends_with' => 'Το πεδίο :attribute πρέπει να τελειώνει με μία από τις ακόλουθες τιμές: :values.',
    'exists' => 'Η επιλεγμένη τιμή για το πεδίο :attribute δεν είναι έγκυρη.',
    'file' => 'Το πεδίο :attribute πρέπει να είναι αρχείο.',
    'filled' => 'Το πεδίο :attribute δεν πρέπει να είναι κενό.',
    'gt' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι μεγαλύτερο από :value.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι μεγαλύτερο από :value kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει περισσότερους από :value χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να περιέχει περισσότερα από :value στοιχεία.',
    ],
    'gte' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι μεγαλύτερο ή ίσο με :value.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι μεγαλύτερο ή ίσο με :value kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει τουλάχιστον :value χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να περιέχει :value ή περισσότερα στοιχεία.',
    ],
    'image' => 'Το πεδίο :attribute πρέπει να είναι εικόνα.',
    'in' => 'Η επιλεγμένη τιμή για το πεδίο :attribute δεν είναι έγκυρη.',
    'in_array' => 'Το πεδίο :attribute δεν υπάρχει στο :other.',
    'integer' => 'Το πεδίο :attribute πρέπει να είναι ακέραιος αριθμός.',
    'ip' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη διεύθυνση IP.',
    'ipv4' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη διεύθυνση IPv4.',
    'ipv6' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη διεύθυνση IPv6.',
    'json' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη συμβολοσειρά JSON.',
    'lt' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι μικρότερο από :value.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι μικρότερο από :value kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει λιγότερους από :value χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να περιέχει λιγότερα από :value στοιχεία.',
    ],
    'lte' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι μικρότερο ή ίσο με :value.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι μικρότερο ή ίσο με :value kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει το πολύ :value χαρακτήρες.',
        'array' => 'Το πεδίο :attribute δεν πρέπει να περιέχει περισσότερα από :value στοιχεία.',
    ],
    'max' => [
        'numeric' => 'Το πεδίο :attribute δεν πρέπει να είναι μεγαλύτερο από :max.',
        'file' => 'Το αρχείο :attribute δεν πρέπει να είναι μεγαλύτερο από :max kilobytes.',
        'string' => 'Το πεδίο :attribute δεν πρέπει να υπερβαίνει τους :max χαρακτήρες.',
        'array' => 'Το πεδίο :attribute δεν πρέπει να περιέχει περισσότερα από :max στοιχεία.',
    ],
    'mimes' => 'Το πεδίο :attribute πρέπει να είναι αρχείο τύπου: :values.',
    'mimetypes' => 'Το πεδίο :attribute πρέπει να είναι αρχείο τύπου: :values.',
    'min' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι τουλάχιστον :min.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι τουλάχιστον :min kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει τουλάχιστον :min χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να περιέχει τουλάχιστον :min στοιχεία.',
    ],
    'not_in' => 'Η επιλεγμένη τιμή για το πεδίο :attribute δεν είναι έγκυρη.',
    'not_regex' => 'Η μορφή του πεδίου :attribute δεν είναι έγκυρη.',
    'numeric' => 'Το πεδίο :attribute πρέπει να είναι αριθμός.',
    'password' => 'Ο κωδικός πρόσβασης είναι λανθασμένος.',
    'password_or_username' => 'Ο κωδικός πρόσβασης ή το όνομα χρήστη είναι λανθασμένο.',
    'present' => 'Το πεδίο :attribute πρέπει να υπάρχει.',
    'regex' => 'Η μορφή του πεδίου :attribute δεν είναι έγκυρη.',
    'required' => 'Το πεδίο :attribute είναι υποχρεωτικό.',
    'required_if' => 'Το πεδίο :attribute είναι υποχρεωτικό όταν το :other είναι :value.',
    'required_unless' => 'Το πεδίο :attribute είναι υποχρεωτικό εκτός εάν το :other βρίσκεται στις τιμές :values.',
    'required_with' => 'Το πεδίο :attribute είναι υποχρεωτικό όταν υπάρχει το :values.',
    'required_with_all' => 'Το πεδίο :attribute είναι υποχρεωτικό όταν υπάρχουν όλες οι τιμές :values.',
    'required_without' => 'Το πεδίο :attribute είναι υποχρεωτικό όταν δεν υπάρχει το :values.',
    'required_without_all' => 'Το πεδίο :attribute είναι υποχρεωτικό όταν δεν υπάρχει καμία από τις τιμές :values.',
    'same' => 'Τα πεδία :attribute και :other πρέπει να ταιριάζουν.',
    'size' => [
        'numeric' => 'Το πεδίο :attribute πρέπει να είναι :size.',
        'file' => 'Το αρχείο :attribute πρέπει να είναι :size kilobytes.',
        'string' => 'Το πεδίο :attribute πρέπει να έχει :size χαρακτήρες.',
        'array' => 'Το πεδίο :attribute πρέπει να περιέχει :size στοιχεία.',
    ],
    'starts_with' => 'Το πεδίο :attribute πρέπει να αρχίζει με μία από τις ακόλουθες τιμές: :values.',
    'string' => 'Το πεδίο :attribute πρέπει να είναι συμβολοσειρά.',
    'timezone' => 'Το πεδίο :attribute πρέπει να είναι έγκυρη ζώνη ώρας.',
    'unique' => 'Η τιμή του πεδίου :attribute χρησιμοποιείται ήδη.',
    'uploaded' => 'Η μεταφόρτωση του πεδίου :attribute απέτυχε.',
    'url' => 'Η μορφή του πεδίου :attribute δεν είναι έγκυρη.',
    'uuid' => 'Το πεδίο :attribute πρέπει να είναι έγκυρο UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'προσαρμοσμένο-μήνυμα',
        ],
    ],

    'captcha' => 'Το captcha είναι λανθασμένο...',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | FlinkISO M6 field labels.
    |
    */

    'attributes' => [
        'preferred_date_format' => 'Μορφή ημερομηνίας',
        'preferred_custom_date_format' => 'Προσαρμοσμένη μορφή ημερομηνίας',
        'preferred_time_format' => 'Μορφή ώρας',
        'preferred_custom_time_format' => 'Προσαρμοσμένη μορφή ώρας',
        'preferred_week_start' => 'Ημέρα έναρξης εβδομάδας',
        'measurement_system' => 'Σύστημα μέτρησης',
        'locale' => 'Γλώσσα',
        'decimal_separator' => 'Δεκαδικό διαχωριστικό',
        'thousands_separator' => 'Διαχωριστικό χιλιάδων',
        'display_precision' => 'Ακρίβεια εμφάνισης',
        'preferred_length_unit' => 'Μονάδα μήκους',
        'preferred_area_unit' => 'Μονάδα επιφάνειας',
        'preferred_mass_unit' => 'Μονάδα μάζας',
        'preferred_speed_unit' => 'Μονάδα ταχύτητας',
        'preferred_temperature_unit' => 'Μονάδα θερμοκρασίας',
        'preferred_force_unit' => 'Μονάδα δύναμης',
        'preferred_volume_unit' => 'Μονάδα όγκου',
        'preferred_energy_unit' => 'Μονάδα ενέργειας',
        'preferred_heat_flow_rate_unit' => 'Μονάδα ρυθμού ροής θερμότητας',
    ],

];
