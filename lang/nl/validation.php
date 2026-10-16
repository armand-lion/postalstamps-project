<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Het veld :attribute moet worden geaccepteerd.',
    'accepted_if' => 'Het veld :attribute moet worden geaccepteerd wanneer :other :value is.',
    'active_url' => 'Het veld :attribute moet een geldige URL zijn.',
    'after' => 'Het veld :attribute moet een datum na :date zijn.',
    'after_or_equal' => 'Het veld :attribute moet een datum na of gelijk aan :date zijn.',
    'alpha' => 'Het veld :attribute mag alleen letters bevatten.',
    'alpha_dash' => 'Het veld :attribute mag uitsluitend letters, cijfers, koppeltekens en underscores bevatten.',
    'alpha_num' => 'Het veld :attribute mag uitsluitend letters en cijfers bevatten.',
    'any_of' => 'Het veld :attribute is ongeldig.',
    'array' => 'Het veld :attribute moet een array zijn.',
    'array_keys' => 'Het veld :attribute mag uitsluitend de volgende keys bevatten: :values.',
    'ascii' => 'Het veld :attribute mag uitsluitend single-byte alphanumeric characters and symbols bevatten.',
    'base64' => 'Het veld :attribute moet een geldige Base64 string zijn.',
    'before' => 'Het veld :attribute moet een datum zijn voor :date.',
    'before_or_equal' => 'Het veld :attribute moet een datum zijn voor of gelijk aan :date.',
    'between' => [
        'array' => 'Het veld :attribute moet tussen de :min en :max items bevatten.',
        'file' => 'Het veld :attribute moet tussen :min en :max kilobytes zijn.',
        'numeric' => 'Het veld :attribute moet tussen :min en :max zijn.',
        'string' => 'Het veld :attribute moet tussen :min en :max karakters zijn.',
    ],
    'boolean' => 'Het veld :attribute moet true of false zijn.',
    'can' => 'Het veld :attribute bevat een niet-geautoriseerde waarde.',
    'confirmed' => 'De bevestiging van het veld :attribute komt niet overeen.',
    'contains' => 'Het veld :attribute mist een vereiste waarde.',
    'current_password' => 'Het wachtwoord is incorrect.',
    'date' => 'Het veld :attribute moet een geldige datum zijn.',
    'date_equals' => 'Het veld :attribute moet een datum zijn gelijk aan :date.',
    'date_format' => 'Het veld :attribute moet voldoen aan het formaat :format.',
    'decimal' => 'Het veld :attribute moet :decimal decimalen bevatten.',
    'declined' => 'Het veld :attribute moet worden afgewezen.',
    'declined_if' => 'Het veld :attribute moet worden afgewezen wanneer :other is :value.',
    'different' => 'Het veld :attribute en :other moeten verschillend zijn.',
    'digits' => 'Het veld :attribute moet :digits cijfers zijn.',
    'digits_between' => 'Het veld :attribute moet tussen :min en :max cijfers zijn.',
    'dimensions' => 'Het veld :attribute heeft ongeldige afbeeldingsdimensies.',
    'distinct' => 'Het veld :attribute heeft een dubbele waarde.',
    'doesnt_contain' => 'Het veld :attribute mag geen van de volgende waarden bevatten: :values.',
    'doesnt_end_with' => 'Het veld :attribute mag niet eindigen met één van de volgende: :values.',
    'doesnt_start_with' => 'Het veld :attribute mag niet beginnen met één van de volgende: :values.',
    'email' => 'Het veld :attribute moet een geldig email adres zijn.',
    'encoding' => 'Het veld :attribute moet geëncodeerd zijn in :encoding.',
    'ends_with' => 'Het veld :attribute moet eindigen met één van de volgende: :values.',
    'enum' => 'Het geselecteerde :attribute is ongeldig.',
    'exists' => 'Het geselecteerde :attribute is ongeldig.',
    'extensions' => 'Het veld :attribute moet één van de volgende extensies hebben: :values.',
    'file' => 'Het veld :attribute moet een bestand zijn.',
    'filled' => 'Het veld :attribute moet een waarde hebben.',
    'gt' => [
        'array' => 'Het veld :attribute moet meer dan :value items hebben.',
        'file' => 'Het veld :attribute moet groter zijn dan :value kilobytes.',
        'numeric' => 'Het veld :attribute moet groter zijn dan :value.',
        'string' => 'Het veld :attribute moet groter zijn dan :value tekens.',
    ],
    'gte' => [
        'array' => 'Het veld :attribute moet :value items of meer hebben.',
        'file' => 'Het veld :attribute moet groter zijn dan of gelijk aan :value kilobytes.',
        'numeric' => 'Het veld :attribute moet groter zijn dan of gelijk aan :value.',
        'string' => 'Het veld :attribute moet groter zijn dan of gelijk aan :value tekens.',
    ],
    'hex_color' => 'Het veld :attribute moet een geldige hexadecimale kleur zijn.',
    'image' => 'Het veld :attribute moet een afbeelding zijn.',
    'in' => 'Het geselecteerde :attribute is ongeldig.',
    'in_array' => 'Het veld :attribute moet bestaan in :other.',
    'in_array_keys' => 'Het veld :attribute moet ten minste één van de volgende keys bevatten: :values.',
    'integer' => 'Het veld :attribute moet een geheel getal zijn.',
    'ip' => 'Het veld :attribute moet een geldig IP-adres zijn.',
    'ipv4' => 'Het veld :attribute moet een geldig IPv4-adres zijn.',
    'ipv6' => 'Het veld :attribute moet een geldig IPv6-adres zijn.',
    'json' => 'Het veld :attribute moet een geldige JSON-string zijn.',
    'list' => 'Het veld :attribute moet een lijst zijn.',
    'lowercase' => 'Het veld :attribute moet in kleine letters zijn.',
    'lt' => [
        'array' => 'Het veld :attribute moet minder dan :value items hebben.',
        'file' => 'Het veld :attribute moet kleiner zijn dan :value kilobytes.',
        'numeric' => 'Het veld :attribute moet kleiner zijn dan :value.',
        'string' => 'Het veld :attribute moet kleiner zijn dan :value tekens.',
    ],
    'lte' => [
        'array' => 'Het veld :attribute mag niet meer dan :value items hebben.',
        'file' => 'Het veld :attribute moet kleiner zijn dan of gelijk aan :value kilobytes.',
        'numeric' => 'Het veld :attribute moet kleiner zijn dan of gelijk aan :value.',
        'string' => 'Het veld :attribute moet kleiner zijn dan of gelijk aan :value tekens.',
    ],
    'mac_address' => 'Het veld :attribute moet een geldig MAC-adres zijn.',
    'max' => [
        'array' => 'Het veld :attribute mag niet meer dan :max items hebben.',
        'file' => 'Het veld :attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => 'Het veld :attribute mag niet groter zijn dan :max.',
        'string' => 'Het veld :attribute mag niet groter zijn dan :max tekens.',
    ],
    'max_digits' => 'Het veld :attribute mag niet meer dan :max cijfers hebben.',
    'mimes' => 'Het veld :attribute moet een bestand zijn van het type: :values.',
    'mimetypes' => 'Het veld :attribute moet een bestand zijn van het type: :values.',
    'min' => [
        'array' => 'Het veld :attribute moet ten minste :min items hebben.',
        'file' => 'Het veld :attribute moet ten minste :min kilobytes zijn.',
        'numeric' => 'Het veld :attribute moet ten minste :min zijn.',
        'string' => 'Het veld :attribute moet ten minste :min tekens hebben.',
    ],
    'min_digits' => 'Het veld :attribute moet ten minste :min cijfers bevatten.',
    'missing' => 'Het veld :attribute moet ontbreken.',
    'missing_if' => 'Het veld :attribute moet ontbreken wanneer :other :value is.',
    'missing_unless' => 'Het veld :attribute moet ontbreken tenzij :other :value is.',
    'missing_with' => 'Het veld :attribute moet ontbreken wanneer :values aanwezig is.',
    'missing_with_all' => 'Het veld :attribute moet ontbreken wanneer :values aanwezig zijn.',
    'multiple_of' => 'Het veld :attribute moet een veelvoud zijn van :value.',
    'not_in' => 'Het geselecteerde :attribute is ongeldig.',
    'not_regex' => 'Het :attribute veld heeft een ongeldig formaat.',
    'numeric' => 'Het :attribute veld moet een getal zijn.',
    'password' => [
        'letters' => 'Het :attribute veld moet minstens één letter bevatten.',
        'mixed' => 'Het :attribute veld moet minstens één hoofdletter en één kleine letter bevatten.',
        'numbers' => 'Het :attribute veld moet minstens één cijfer bevatten.',
        'symbols' => 'Het :attribute veld moet minstens één symbool bevatten.',
        'uncompromised' => 'Het opgegeven :attribute is verschenen in een datalek. Kies een ander :attribute.',
    ],
    'present' => 'Het veld :attribute moet aanwezig zijn.',
    'present_if' => 'Het veld :attribute moet aanwezig zijn wanneer :other :value is.',
    'present_unless' => 'Het veld :attribute moet aanwezig zijn tenzij :other :value is.',
    'present_with' => 'Het veld :attribute moet aanwezig zijn wanneer :values aanwezig is.',
    'present_with_all' => 'Het veld :attribute moet aanwezig zijn wanneer :values aanwezig zijn.',
    'prohibited' => 'Het veld :attribute is verboden.',
    'prohibited_if' => 'Het veld :attribute is verboden wanneer :other :value is.',
    'prohibited_if_accepted' => 'Het veld :attribute is verboden wanneer :other is geaccepteerd.',
    'prohibited_if_declined' => 'Het veld :attribute is verboden wanneer :other is afgewezen.',
    'prohibited_unless' => 'Het veld :attribute is verboden tenzij :other is in :values.',
    'prohibits' => 'Het veld :attribute verbiedt :other om aanwezig te zijn.',
    'regex' => 'Het veld :attribute heeft een ongeldig formaat.',
    'required' => 'Het veld :attribute is verplicht.',
    'required_array_keys' => 'Het veld :attribute moet entries bevatten voor: :values.',
    'required_if' => 'Het veld :attribute is verplicht wanneer :other is :value.',
    'required_if_accepted' => 'Het veld :attribute is verplicht wanneer :other is geaccepteerd.',
    'required_if_declined' => 'Het veld :attribute is verplicht wanneer :other is afgewezen.',
    'required_unless' => 'Het veld :attribute is verplicht tenzij :other is in :values.',
    'required_with' => 'Het veld :attribute is verplicht wanneer :values aanwezig is.',
    'required_with_all' => 'Het veld :attribute is verplicht wanneer :values aanwezig zijn.',
    'required_without' => 'Het veld :attribute is verplicht wanneer :values niet aanwezig is.',
    'required_without_all' => 'Het veld :attribute is verplicht wanneer geen van :values aanwezig is.',
    'same' => 'Het veld :attribute moet overeenkomen met :other.',
    'size' => [
        'array' => 'Het veld :attribute moet :size items bevatten.',
        'file' => 'Het veld :attribute moet :size kilobytes zijn.',
        'numeric' => 'Het veld :attribute moet :size zijn.',
        'string' => 'Het veld :attribute moet :size karakters zijn.',
    ],
    'starts_with' => 'Het veld :attribute moet beginnen met één van de volgende: :values.',
    'string' => 'Het veld :attribute moet een string zijn.',
    'timezone' => 'Het veld :attribute moet een geldige tijdzone zijn.',
    'unique' => 'Het veld :attribute is al in gebruik.',
    'uploaded' => 'Het veld :attribute kon niet worden geüpload.',
    'uppercase' => 'Het veld :attribute moet in hoofdletters zijn.',
    'url' => 'Het veld :attribute moet een geldige URL zijn.',
    'ulid' => 'Het veld :attribute moet een geldige ULID zijn.',
    'uuid' => 'Het veld :attribute moet een geldige UUID zijn.',

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
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
