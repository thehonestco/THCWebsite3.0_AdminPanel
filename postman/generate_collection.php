<?php

declare(strict_types=1);

function envValue(string $key, string $default = ''): string
{
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath)) {
        return $default;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        if (trim($name) !== $key) {
            continue;
        }

        return trim($value, " \t\n\r\0\x0B\"");
    }

    return $default;
}

function urlFor(string $baseUrlVariable, string $path, array $query = []): array
{
    $raw = $baseUrlVariable . '/' . ltrim($path, '/');

    if ($query !== []) {
        $pairs = [];

        foreach ($query as $key => $value) {
            $pairs[] = $key . '=' . rawurlencode((string) $value);
        }

        $raw .= '?' . implode('&', $pairs);
    }

    return [
        'raw' => $raw,
        'host' => [$baseUrlVariable],
        'path' => array_values(array_filter(explode('/', ltrim($path, '/')))),
        'query' => array_map(
            fn ($key, $value) => [
                'key' => (string) $key,
                'value' => (string) $value,
            ],
            array_keys($query),
            array_values($query)
        ),
    ];
}

function requestItem(
    string $name,
    string $method,
    string $path,
    array $options = []
): array {
    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;
    $query = $options['query'] ?? [];
    $auth = $options['auth'] ?? ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{bearer_token}}', 'type' => 'string']]];
    $description = $options['description'] ?? '';

    $request = [
        'method' => strtoupper($method),
        'header' => $headers,
        'url' => urlFor('{{base_url}}', $path, $query),
        'description' => $description,
        'auth' => $auth,
    ];

    if ($body !== null) {
        $request['body'] = $body;
    }

    $item = [
        'name' => $name,
        'request' => $request,
        'response' => [],
    ];

    if (isset($options['events'])) {
        $item['event'] = $options['events'];
    }

    return $item;
}

function jsonBody(array $payload): array
{
    return [
        'mode' => 'raw',
        'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'options' => [
            'raw' => [
                'language' => 'json',
            ],
        ],
    ];
}

function formDataBody(array $fields): array
{
    $formdata = [];

    foreach ($fields as $field) {
        $entry = [
            'key' => $field['key'],
            'type' => $field['type'] ?? 'text',
        ];

        if (array_key_exists('value', $field)) {
            $entry['value'] = (string) $field['value'];
        }

        if (isset($field['src'])) {
            $entry['src'] = $field['src'];
        }

        if (isset($field['description'])) {
            $entry['description'] = $field['description'];
        }

        $formdata[] = $entry;
    }

    return [
        'mode' => 'formdata',
        'formdata' => $formdata,
    ];
}

$baseUrl = rtrim(envValue('APP_URL', 'http://localhost'), '/') . '/api';

$collection = [
    'info' => [
        'name' => 'THCWebsite3.0 Admin Panel API',
        '_postman_id' => '3d71fbf1-31b7-4d6b-85bd-fbc0f11f4f35',
        'description' => "Import-ready collection generated from routes/api.php and controller validation rules.\n\n1. Run the `Auth / Login` request first.\n2. It will automatically save the returned Sanctum token into the `bearer_token` collection variable.\n3. Update the placeholder IDs only after you create records in your environment.\n\nProtected endpoints also require matching role/permission access in the application.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => $baseUrl],
        ['key' => 'bearer_token', 'value' => ''],
        ['key' => 'user_id', 'value' => '1'],
        ['key' => 'role_id', 'value' => '1'],
        ['key' => 'permission_id', 'value' => '1'],
        ['key' => 'jd_id', 'value' => '1'],
        ['key' => 'position_id', 'value' => '1'],
        ['key' => 'applicant_id', 'value' => '1'],
        ['key' => 'application_id', 'value' => '1'],
        ['key' => 'lead_id', 'value' => '1'],
        ['key' => 'opportunity_id', 'value' => '1'],
        ['key' => 'note_id', 'value' => '1'],
        ['key' => 'bank_detail_id', 'value' => '1'],
        ['key' => 'invoice_id', 'value' => '1'],
        ['key' => 'template_id', 'value' => '1'],
        ['key' => 'smtp_id', 'value' => '1'],
        ['key' => 'media_id', 'value' => '1'],
        ['key' => 'resource_id', 'value' => '1'],
        ['key' => 'editor_id', 'value' => '1'],
        ['key' => 'job_id', 'value' => '1'],
    ],
    'item' => [],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [
            [
                'key' => 'token',
                'value' => '{{bearer_token}}',
                'type' => 'string',
            ],
        ],
    ],
];

$jsonHeaders = [['key' => 'Content-Type', 'value' => 'application/json']];

$collection['item'][] = [
    'name' => 'Auth',
    'item' => [
        requestItem('Login', 'POST', 'login', [
            'auth' => ['type' => 'noauth'],
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'email' => 'admin@example.com',
                'password' => '12345678',
            ]),
            'events' => [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'pm.test("Login successful", function () {',
                        '    pm.response.to.have.status(200);',
                        '});',
                        'const json = pm.response.json();',
                        'if (json.token) {',
                        '    pm.collectionVariables.set("bearer_token", json.token);',
                        '}',
                    ],
                ],
            ]],
        ]),
        requestItem('Me', 'GET', 'me'),
        requestItem('Logout', 'POST', 'logout'),
    ],
];

$collection['item'][] = [
    'name' => 'Public',
    'item' => [
        requestItem('Public Lead Submit', 'POST', 'public/lead', [
            'auth' => ['type' => 'noauth'],
            'body' => formDataBody([
                ['key' => 'name', 'value' => 'John Doe'],
                ['key' => 'email', 'value' => 'john@example.com'],
                ['key' => 'phone', 'value' => '+919999999999'],
                ['key' => 'form_type', 'value' => 'Website Inquiry'],
                ['key' => 'source', 'value' => 'Website'],
                ['key' => 'opportunity_description', 'value' => 'Need help with web development services.'],
                ['key' => 'notes', 'value' => 'Please contact during business hours.'],
                ['key' => 'files[0]', 'type' => 'file', 'src' => ''],
            ]),
        ]),
        requestItem('Public Jobs List', 'GET', 'public/jobs', [
            'auth' => ['type' => 'noauth'],
        ]),
        requestItem('Public Job Detail', 'GET', 'public/jobs/{{job_id}}', [
            'auth' => ['type' => 'noauth'],
        ]),
        requestItem('Public Job Apply', 'POST', 'public/jobs/{{position_id}}/apply', [
            'auth' => ['type' => 'noauth'],
            'body' => formDataBody([
                ['key' => 'name', 'value' => 'Candidate Name'],
                ['key' => 'email', 'value' => 'candidate@example.com'],
                ['key' => 'phone', 'value' => '+919876543210'],
                ['key' => 'experience_years', 'value' => '4'],
                ['key' => 'current_ctc', 'value' => '8'],
                ['key' => 'expected_ctc', 'value' => '10'],
                ['key' => 'notice_period_days', 'value' => '30'],
                ['key' => 'resume', 'type' => 'file', 'src' => ''],
            ]),
        ]),
    ],
];

$collection['item'][] = [
    'name' => 'Permissions',
    'item' => [
        requestItem('My Permissions', 'GET', 'permissions'),
        requestItem('All Permissions', 'GET', 'permissions/all'),
    ],
];

$collection['item'][] = [
    'name' => 'Users',
    'item' => [
        requestItem('List Users', 'GET', 'users', [
            'query' => [
                'per_page' => '10',
                'search' => '',
                'status' => 'active',
            ],
        ]),
        requestItem('Create User', 'POST', 'users', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'New Team Member',
                'email' => 'new.user@example.com',
                'password' => '12345678',
                'role_id' => 1,
                'phone' => '+919811111111',
                'designation' => 'Executive',
                'department' => 'Business',
                'linkedin_url' => 'https://linkedin.com/in/new-user',
                'tags' => 'sales,client-success',
                'type' => 'employed',
                'permissions' => [1, 2],
            ]),
        ]),
        requestItem('Show User', 'GET', 'users/{{user_id}}'),
        requestItem('Update User', 'PUT', 'users/{{user_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Updated Team Member',
                'email' => 'updated.user@example.com',
                'password' => '12345678',
                'role_id' => 1,
                'phone' => '+919822222222',
                'designation' => 'Senior Executive',
                'department' => 'Business',
                'linkedin_url' => 'https://linkedin.com/in/updated-user',
                'tags' => 'sales,key-account',
                'type' => 'employed',
                'permissions' => [1, 2],
                'is_active' => true,
            ]),
        ]),
        requestItem('Delete User', 'DELETE', 'users/{{user_id}}'),
        requestItem('Restore User', 'POST', 'users/{{user_id}}/restore'),
    ],
];

$collection['item'][] = [
    'name' => 'Job Descriptions',
    'item' => [
        requestItem('List Job Descriptions', 'GET', 'job-descriptions', [
            'query' => ['status' => 'active'],
        ]),
        requestItem('Create Job Description', 'POST', 'job-descriptions', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'title' => 'Laravel Developer',
                'status' => 'active',
                'about_job' => 'We are hiring a Laravel developer.',
                'key_skills' => 'Laravel, PHP, MySQL',
                'responsibilities' => 'Build and maintain web apps.',
                'interview_process' => 'HR Round > Technical Round > Final Round',
            ]),
        ]),
        requestItem('Show Job Description', 'GET', 'job-descriptions/{{jd_id}}'),
        requestItem('Update Job Description', 'PUT', 'job-descriptions/{{jd_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'title' => 'Senior Laravel Developer',
                'status' => 'active',
                'about_job' => 'We are hiring a senior Laravel developer.',
                'key_skills' => 'Laravel, PHP, MySQL, AWS',
                'responsibilities' => 'Lead backend development.',
                'interview_process' => 'HR Round > Technical Panel > Final Round',
            ]),
        ]),
        requestItem('Delete Job Description', 'DELETE', 'job-descriptions/{{jd_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Positions',
    'item' => [
        requestItem('List Positions', 'GET', 'positions'),
        requestItem('Create Position', 'POST', 'positions', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'position_name' => 'Backend Engineer',
                'job_description_id' => 1,
                'job_type' => 'Full Time',
                'work_mode' => 'Hybrid',
                'city' => 'Noida',
                'country' => 'India',
                'skills' => ['Laravel', 'MySQL', 'REST API'],
                'experience_min' => 2,
                'experience_max' => 5,
                'salary_min' => 600000,
                'salary_max' => 1200000,
                'status' => 'open',
                'applicant_ids' => [1],
            ]),
        ]),
        requestItem('Show Position', 'GET', 'positions/{{position_id}}'),
        requestItem('Update Position', 'PUT', 'positions/{{position_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'position_name' => 'Senior Backend Engineer',
                'job_type' => 'Full Time',
                'work_mode' => 'Remote',
                'city' => 'Delhi',
                'country' => 'India',
                'skills' => ['Laravel', 'MySQL', 'AWS'],
                'experience_min' => 3,
                'experience_max' => 6,
                'salary_min' => 900000,
                'salary_max' => 1500000,
                'status' => 'open',
            ]),
        ]),
        requestItem('Delete Position', 'DELETE', 'positions/{{position_id}}'),
        requestItem('Add Applicants To Position', 'POST', 'positions/{{position_id}}/applicants', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'applicant_ids' => [1, 2],
            ]),
        ]),
        requestItem('Update Position Applicant', 'PUT', 'positions/{{position_id}}/applicants/{{application_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'experience_years' => 4,
                'current_ctc' => 8,
                'expected_ctc' => 12,
                'notice_period_days' => 30,
                'stage' => 'tech_round',
                'comment' => 'Candidate cleared screening round.',
            ]),
        ]),
        requestItem('Remove Position Applicant', 'DELETE', 'positions/{{position_id}}/applicants/{{application_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Applicants',
    'item' => [
        requestItem('List Applicants', 'GET', 'applicants', [
            'query' => [
                'status' => 'active',
                'search' => '',
            ],
        ]),
        requestItem('Create Applicant', 'POST', 'applicants', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Ankit Sharma',
                'phone' => '+919811112222',
                'email' => 'ankit.sharma@example.com',
                'linkedin_url' => 'https://linkedin.com/in/ankit-sharma',
                'skills' => 'Laravel, PHP, MySQL',
                'status' => 'active',
                'position_ids' => [1],
            ]),
        ]),
        requestItem('Show Applicant', 'GET', 'applicants/{{applicant_id}}'),
        requestItem('Update Applicant', 'PUT', 'applicants/{{applicant_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Ankit Sharma',
                'phone' => '+919811113333',
                'email' => 'ankit.updated@example.com',
                'linkedin_url' => 'https://linkedin.com/in/ankit-sharma',
                'skills' => 'Laravel, PHP, MySQL, AWS',
                'status' => 'active',
            ]),
        ]),
        requestItem('Delete Applicant', 'DELETE', 'applicants/{{applicant_id}}'),
        requestItem('Add Positions To Applicant', 'POST', 'applicants/{{applicant_id}}/positions', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'position_ids' => [1, 2],
            ]),
        ]),
    ],
];

$collection['item'][] = [
    'name' => 'Leads And Clients',
    'item' => [
        requestItem('List Leads', 'GET', 'leads', [
            'query' => ['per_page' => '10'],
        ]),
        requestItem('Create Lead', 'POST', 'leads', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'company_name' => 'Acme Pvt Ltd',
                'company_website' => 'https://acme.example.com',
                'company_linkedin' => 'https://linkedin.com/company/acme',
                'city' => 'Mumbai',
                'country' => 'India',
                'tags' => 'enterprise,priority',
                'source' => 'Website',
                'poc_name' => 'Rahul Mehta',
                'poc_email' => 'rahul.mehta@example.com',
                'poc_phone' => '+919800000001',
                'poc_linkedin' => 'https://linkedin.com/in/rahul-mehta',
            ]),
        ]),
        requestItem('Show Lead', 'GET', 'leads/{{lead_id}}'),
        requestItem('Update Lead', 'PUT', 'leads/{{lead_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'company_name' => 'Acme Technologies Pvt Ltd',
                'company_website' => 'https://acme.example.com',
                'company_linkedin' => 'https://linkedin.com/company/acme',
                'city' => 'Mumbai',
                'country' => 'India',
                'tags' => 'enterprise,key-account',
                'source' => 'Referral',
                'poc_name' => 'Rahul Mehta',
                'poc_email' => 'rahul.mehta@example.com',
                'poc_phone' => '+919800000002',
                'poc_linkedin' => 'https://linkedin.com/in/rahul-mehta',
                'business_details' => [
                    'business_name' => 'Acme Technologies Pvt Ltd',
                    'gst_number' => '22AAAAA0000A1Z5',
                    'pan_number' => 'AAAAA0000A',
                    'address' => 'Business Bay, Mumbai',
                ],
            ]),
        ]),
        requestItem('Delete Lead', 'DELETE', 'leads/{{lead_id}}'),
        requestItem('Bulk Upload Leads', 'POST', 'leads/bulk-upload', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'leads' => [
                    [
                        'organization_name' => 'Globex Corporation',
                        'company_website' => 'https://globex.example.com',
                        'company_linkedin' => 'https://linkedin.com/company/globex',
                        'city' => 'Bengaluru',
                        'country' => 'India',
                        'tag_1' => 'saas',
                        'tag_2' => 'inbound',
                        'poc_name' => 'Sana Ali',
                        'poc_email' => 'sana.ali@example.com',
                        'poc_number' => '+919700000001',
                        'poc_linkedin' => 'https://linkedin.com/in/sana-ali',
                        'source' => 'Conference',
                    ],
                ],
            ]),
        ]),
        requestItem('Convert Lead To Client', 'POST', 'leads/{{lead_id}}/convert'),
        requestItem('List Clients', 'GET', 'clients', [
            'query' => ['per_page' => '10'],
        ]),
    ],
];

$collection['item'][] = [
    'name' => 'Opportunities',
    'item' => [
        requestItem('List Opportunities For Lead', 'GET', 'leads/{{lead_id}}/opportunities'),
        requestItem('Create Opportunity', 'POST', 'leads/{{lead_id}}/opportunities', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'title' => 'Website Revamp',
                'description' => 'Corporate website redesign and development.',
                'amount' => 250000,
                'owner_name' => 'Will be overridden by logged in user',
                'stage' => 'proposal_sent',
                'status' => 'open',
            ]),
        ]),
        requestItem('Show Opportunity', 'GET', 'opportunities/{{opportunity_id}}'),
        requestItem('Update Opportunity', 'PUT', 'opportunities/{{opportunity_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'title' => 'Website Revamp Phase 1',
                'description' => 'Updated scope for phase 1.',
                'amount' => 300000,
                'owner_name' => 'Will be overridden by logged in user',
                'stage' => 'negotiation',
                'status' => 'open',
            ]),
        ]),
        requestItem('Delete Opportunity', 'DELETE', 'opportunities/{{opportunity_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Notes',
    'item' => [
        requestItem('List Notes For Opportunity', 'GET', 'opportunities/{{opportunity_id}}/notes'),
        requestItem('Create Note', 'POST', 'opportunities/{{opportunity_id}}/notes', [
            'body' => formDataBody([
                ['key' => 'content', 'value' => 'Client call completed successfully.'],
                ['key' => 'opportunity_stage', 'value' => 'proposal_sent'],
                ['key' => 'attachments[0]', 'type' => 'file', 'src' => ''],
            ]),
        ]),
        requestItem('Show Note', 'GET', 'notes/{{note_id}}'),
        requestItem('Update Note', 'PUT', 'notes/{{note_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'comment' => 'Updated meeting notes after follow-up.',
                'opportunity_stage' => 'negotiation',
            ]),
        ]),
        requestItem('Delete Note', 'DELETE', 'notes/{{note_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Bank Details',
    'item' => [
        requestItem('List Bank Details', 'GET', 'bank-details'),
        requestItem('Create Bank Detail', 'POST', 'bank-details', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'bank_name' => 'HDFC Bank',
                'account_holder_name' => 'THC Private Limited',
                'account_number' => '1234567890',
                'ifsc_code' => 'HDFC0001234',
                'swift_code' => 'HDFCINBB',
                'account_type' => 'Current',
            ]),
        ]),
        requestItem('Show Bank Detail', 'GET', 'bank-details/{{bank_detail_id}}'),
        requestItem('Update Bank Detail', 'PUT', 'bank-details/{{bank_detail_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'bank_name' => 'ICICI Bank',
                'account_holder_name' => 'THC Private Limited',
                'account_number' => '1234567890',
                'ifsc_code' => 'ICIC0001234',
                'swift_code' => 'ICICINBB',
                'account_type' => 'Current',
            ]),
        ]),
        requestItem('Delete Bank Detail', 'DELETE', 'bank-details/{{bank_detail_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Invoices',
    'item' => [
        requestItem('List Invoices', 'GET', 'invoices'),
        requestItem('Create Invoice', 'POST', 'invoices', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'type' => 'proforma',
                'lead_id' => 1,
                'bank_detail_id' => 1,
                'cgst' => 900,
                'sgst' => 900,
                'items' => [
                    [
                        'description' => 'Website design',
                        'amount' => 10000,
                    ],
                    [
                        'description' => 'Development',
                        'amount' => 15000,
                    ],
                ],
            ]),
        ]),
        requestItem('Show Invoice', 'GET', 'invoices/{{invoice_id}}'),
        requestItem('Delete Invoice', 'DELETE', 'invoices/{{invoice_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Resources',
    'item' => [
        requestItem('Resources Metadata', 'GET', 'resources/metadata'),
        requestItem('List Resources', 'GET', 'resources', [
            'query' => [
                'search' => '',
                'category' => 'our-work',
                'status' => 'draft',
                'edited_by' => '{{editor_id}}',
                'date_from' => '',
                'date_to' => '',
                'per_page' => '10',
            ],
        ]),
        requestItem('Create Resource', 'POST', 'resources', [
            'body' => formDataBody([
                ['key' => 'resource_type', 'value' => 'our-work'],
                ['key' => 'sub_industry', 'value' => 'sub-cat-a'],
                ['key' => 'sub_service', 'value' => 'sub-menu-a'],
                ['key' => 'listing_title', 'value' => 'P2P Money Remittance Mobile Application'],
                ['key' => 'listing_description', 'value' => 'To Peer Payments Through Money Remittance Platforms.'],
                ['key' => 'status', 'value' => 'draft'],
                ['key' => 'listing_image', 'type' => 'file', 'src' => ''],
            ]),
        ]),
        requestItem('Show Resource', 'GET', 'resources/{{resource_id}}'),
        requestItem('Update Resource', 'POST', 'resources/{{resource_id}}', [
            'body' => formDataBody([
                ['key' => 'resource_type', 'value' => 'our-work'],
                ['key' => 'sub_industry', 'value' => 'sub-cat-b'],
                ['key' => 'sub_service', 'value' => 'sub-menu-b'],
                ['key' => 'listing_title', 'value' => 'Updated P2P Money Remittance Mobile Application'],
                ['key' => 'listing_description', 'value' => 'Updated listing description for frontend use.'],
                ['key' => 'status', 'value' => 'published'],
                ['key' => 'listing_image', 'type' => 'file', 'src' => ''],
                ['key' => 'resource_payload', 'value' => json_encode([
                    'resourceType' => 'our-work',
                    'sections' => [
                        [
                            'id' => 'breadcrumb-section',
                            'type' => 'breadcrumb',
                            'content' => [
                                'items' => [
                                    'Portfolio Projects',
                                    'Updated P2P Money Remittance Mobile Application',
                                ],
                            ],
                        ],
                        [
                            'id' => 'edge-section',
                            'type' => 'edge',
                            'content' => [
                                'title' => 'The Honest Edge',
                                'description1' => 'We work with clients across a range of industries.',
                                'description2' => 'Description 2',
                                'image' => '',
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES)],
            ]),
        ]),
        requestItem('Delete Resource', 'DELETE', 'resources/{{resource_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Templates',
    'item' => [
        requestItem('List Templates', 'GET', 'templates'),
        requestItem('Create Template', 'POST', 'templates', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Welcome Email',
                'content' => 'Hello {{name}}, welcome aboard.',
                'status' => 'Active',
            ]),
        ]),
        requestItem('Show Template', 'GET', 'templates/{{template_id}}'),
        requestItem('Update Template', 'PUT', 'templates/{{template_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Follow-up Email',
                'content' => 'Hello {{name}}, just following up on our discussion.',
                'status' => 'Pending',
            ]),
        ]),
        requestItem('Delete Template', 'DELETE', 'templates/{{template_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'SMTP Settings',
    'item' => [
        requestItem('List SMTP Settings', 'GET', 'smtp-settings'),
        requestItem('Create SMTP Setting', 'POST', 'smtp-settings', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Primary SMTP',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'username' => 'smtp-user@example.com',
                'password' => 'secret-password',
                'encryption' => 'tls',
                'from_email' => 'noreply@example.com',
                'from_name' => 'THC Admin',
                'is_active' => true,
                'is_default' => true,
            ]),
        ]),
        requestItem('Show SMTP Setting', 'GET', 'smtp-settings/{{smtp_id}}'),
        requestItem('Update SMTP Setting', 'PUT', 'smtp-settings/{{smtp_id}}', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'name' => 'Secondary SMTP',
                'host' => 'smtp.office365.com',
                'port' => 587,
                'username' => 'smtp-user@example.com',
                'password' => 'updated-secret',
                'encryption' => 'tls',
                'from_email' => 'support@example.com',
                'from_name' => 'THC Support',
                'is_active' => true,
                'is_default' => false,
            ]),
        ]),
        requestItem('Delete SMTP Setting', 'DELETE', 'smtp-settings/{{smtp_id}}'),
    ],
];

$collection['item'][] = [
    'name' => 'Media Center',
    'item' => [
        requestItem('List Media Center Assets', 'GET', 'media-center', [
            'query' => [
                'search' => '',
                'date' => '',
                'type' => 'image',
                'status' => 'active',
                'editor_id' => '{{editor_id}}',
                'date_from' => '',
                'date_to' => '',
                'per_page' => '10',
            ],
        ]),
        requestItem('Create Media Asset', 'POST', 'media-center', [
            'body' => formDataBody([
                ['key' => 'status', 'value' => 'active'],
                ['key' => 'files[0]', 'type' => 'file', 'src' => ''],
            ]),
        ]),
        requestItem('Upload Media Asset Alias', 'POST', 'media-center/upload', [
            'body' => formDataBody([
                ['key' => 'status', 'value' => 'inactive'],
                ['key' => 'files[0]', 'type' => 'file', 'src' => ''],
            ]),
        ]),
        requestItem('Show Media Asset', 'GET', 'media-center/{{media_id}}'),
        requestItem('Update Media Status', 'PATCH', 'media-center/{{media_id}}/status', [
            'headers' => $jsonHeaders,
            'body' => jsonBody([
                'status' => 'active',
            ]),
        ]),
        requestItem('Delete Media Asset', 'DELETE', 'media-center/{{media_id}}'),
    ],
];

$outputPath = __DIR__ . DIRECTORY_SEPARATOR . 'THCWebsite3.0_AdminPanel_API.postman_collection.json';

file_put_contents(
    $outputPath,
    json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Collection generated at: {$outputPath}" . PHP_EOL;
