<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Requests;
use App\Models\User;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $departments = Department::all();

        if ($users->isEmpty() || $departments->isEmpty()) {
            $this->command->warn('No users or departments found. Please run UserSeeder and DepartmentSeeder first.');

            return;
        }

        $statuses = [
            'Pending' => 20,
            'Claimed' => 15,
            'Awaiting Update' => 15,
            'In Review' => 15,
            'Complete' => 20,
            'Rejected' => 8,
        ];

        foreach ($statuses as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $requiresScan = in_array($status, ['Awaiting Update', 'In Review']) ? true : fake()->boolean(40);

                $scanReviewStatus = null;
                $scanReviewNotes = null;

                if ($status === 'Awaiting Update') {
                    $scanReviewStatus = 'Rejected';
                    $scanReviewNotes = fake()->randomElement([
                        'Scan was blurry, please rescan.',
                        'Filename did not match the linked record.',
                        'Incomplete document, missing signature page.',
                    ]);
                }

                if ($status === 'Complete' && $requiresScan) {
                    $scanReviewStatus = 'Approved';
                }

                Requests::factory()->create([
                    'request_id' => fake()->uuid(),
                    'department_id' => $departments->random()->id,
                    'requested_by' => $users->random()->id,
                    'status' => $status,
                    'document_type' => fake()->randomElement(['Correspondence', 'Financial Records', 'Personnel', 'Legal', 'Administrative']),
                    'file_number' => fake()->bothify('FN-####-????'),
                    'request_title' => $this->generateRequestTitle(),
                    'purpose' => $this->generatePurpose(),
                    'priority' => fake()->randomElement(['standard', 'urgent']),
                    'required_format' => fake()->randomElement(['Retrieve Original Copy', 'Reproduce Hardcopy', 'Electronic Copy Only']),
                    'start_date' => fake()->optional(0.7)->dateTimeBetween('-2 years', 'now'),
                    'end_date' => fake()->optional(0.7)->dateTimeBetween('now', '+1 year'),
                    'notes' => $status === 'Rejected'
                        ? fake()->randomElement([
                            'Document not found in archive',
                            'Insufficient information provided',
                            'Request does not meet requirements',
                            'Duplicate request',
                        ])
                        : ($status === 'Complete' ? 'Request fulfilled successfully' : null),
                    'created_at' => fake()->dateTimeBetween('-90 days', 'now'),
                    'requires_scan' => $requiresScan,
                    'scan_review_status' => $scanReviewStatus,
                    'scan_review_notes' => $scanReviewNotes,
                ]);
            }
        }
    }

    /**
     * Generate a realistic request title.
     */
    private function generateRequestTitle(): string
    {
        $titles = [
            'Budget Report Request',
            'Personnel File Access',
            'Certified Copy of Resolution',
            'Historical Records Request',
            'Financial Audit Documents',
            'Meeting Minutes Request',
            'Contract Documentation',
            'Employee Records Request',
            'Legal Document Copy',
            'Archived Correspondence',
            'Annual Report Request',
            'Policy Document Access',
            'Training Records Request',
            'Project Documentation',
            'Procurement Records',
            'Memorandum Copy Request',
            'Departmental Report',
            'Compliance Documents',
            'Board Resolution Copy',
            'Administrative Records',
        ];

        return fake()->randomElement($titles);
    }

    /**
     * Generate a realistic description.
     */
    private function generateDescription(): string
    {
        $descriptions = [
            'Request for official copy of document for audit purposes',
            'Need access to archived files for current project reference',
            'Requesting certified copy for legal proceedings',
            'Historical records needed for research and documentation',
            'Copy of financial documents required for compliance review',
            'Official records required for verification purposes',
            'Documents needed for department internal review',
            'Archived materials requested for ongoing investigation',
            'Copy of official correspondence for reference',
            'Records needed for policy development purposes',
        ];

        return fake()->randomElement($descriptions);
    }

    /**
     * Generate a realistic purpose.
     */
    private function generatePurpose(): string
    {
        $purposes = [
            'Audit and compliance verification',
            'Legal proceedings and documentation',
            'Research and historical reference',
            'Internal review and analysis',
            'Policy development and planning',
            'Training and educational purposes',
            'Project reference and planning',
            'Verification and validation',
            'Regulatory compliance',
            'Administrative requirements',
        ];

        return fake()->randomElement($purposes);
    }
}
