<?php

namespace App\Http\Requests;

use App\Support\PersonNameFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApprovalLetterPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'letter_reference' => ['nullable', 'string', 'max:100'],
            'letter_date' => ['required', 'string', 'max:100'],
            'actual_disposal_date' => ['required', 'string', 'max:100'],
            'recipient_first_name' => ['required', 'string', 'max:255'],
            'recipient_middle_name' => ['nullable', 'string', 'max:255'],
            'recipient_last_name' => ['required', 'string', 'max:255'],
            'recipient_extension' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_position' => ['nullable', 'string', 'max:255'],
            'recipient_designation' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:500'],
            'honorific' => ['nullable', 'string', 'max:50'],
            'pictures' => ['nullable', 'array', 'max:'.ApproveDisposalBatchRequest::MAX_PICTURES],
            'pictures.*' => ['file', 'image', 'max:5120'],
            'removed_documents' => ['nullable', 'array', 'max:'.ApproveDisposalBatchRequest::MAX_PICTURES],
            'removed_documents.*' => ['string', 'max:500'],
            'nap_documents' => ['required', 'array', 'min:1', 'max:15'],
            'nap_documents.*.id' => ['required', 'string', 'max:100'],
            'nap_documents.*.label' => ['required', 'string', 'max:255'],
            'nap_documents.*.existing_path' => ['nullable', 'string', 'max:500'],
            'nap_documents.*.original_name' => ['nullable', 'string', 'max:255'],
            'nap_documents.*.uploaded_at' => ['nullable', 'string', 'max:100'],
            'nap_documents.*.file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $documents = $this->input('nap_documents', []);

            if (! is_array($documents) || $documents === []) {
                return;
            }

            $missingLabels = [];

            foreach ($documents as $index => $document) {
                if (! is_array($document)) {
                    continue;
                }

                $hasExistingPath = isset($document['existing_path']) && is_string($document['existing_path']) && trim($document['existing_path']) !== '';
                $hasUploadedFile = $this->hasFile("nap_documents.$index.file");

                if (! $hasExistingPath && ! $hasUploadedFile) {
                    $label = trim((string) ($document['label'] ?? ''));
                    $missingLabels[] = $label !== '' ? $label : 'Supporting Document '.($index + 1);
                }
            }

            if ($missingLabels !== []) {
                $validator->errors()->add('nap_documents', 'Upload a PDF for each supporting document: '.implode(', ', $missingLabels).'.');
            }
        });
    }

    /**
     * @return array{
     *     letter_reference: string|null,
     *     letter_date: string,
     *     actual_disposal_date: string,
     *     recipient_first_name: string,
     *     recipient_middle_name: string|null,
     *     recipient_last_name: string,
     *     recipient_extension: string|null,
     *     recipient_name: string,
     *     recipient_position: string|null,
     *     recipient_designation: string|null,
     *     recipient_address: string|null,
     *     honorific: string|null,
     *     nap_documents: list<array{
     *         id: string,
     *         label: string,
     *         existing_path: string|null,
     *         original_name: string|null,
     *         uploaded_at: string|null,
     *     }>
     * }
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $recipientName = PersonNameFormatter::compose(
            $validated['recipient_first_name'] ?? '',
            $validated['recipient_middle_name'] ?? null,
            $validated['recipient_last_name'] ?? '',
            $validated['recipient_extension'] ?? null,
        );

        return [
            'letter_reference' => $validated['letter_reference'] ?? null,
            'letter_date' => $validated['letter_date'],
            'actual_disposal_date' => $validated['actual_disposal_date'],
            'recipient_first_name' => $validated['recipient_first_name'],
            'recipient_middle_name' => $validated['recipient_middle_name'] ?? null,
            'recipient_last_name' => $validated['recipient_last_name'],
            'recipient_extension' => $validated['recipient_extension'] ?? null,
            'recipient_name' => $recipientName,
            'recipient_position' => $validated['recipient_position'] ?? null,
            'recipient_designation' => $validated['recipient_designation'] ?? null,
            'recipient_address' => $validated['recipient_address'] ?? null,
            'honorific' => $validated['honorific'] ?? null,
            'pictures' => $this->file('pictures', []),
            'removed_documents' => array_values(array_filter(
                $validated['removed_documents'] ?? [],
                static fn ($path): bool => is_string($path) && $path !== '',
            )),
            'nap_documents' => array_map(
                static fn (array $document): array => [
                    'id' => $document['id'],
                    'label' => trim((string) $document['label']),
                    'existing_path' => $document['existing_path'] ?? null,
                    'original_name' => $document['original_name'] ?? null,
                    'uploaded_at' => $document['uploaded_at'] ?? null,
                    'file' => $document['file'] ?? null,
                ],
                $validated['nap_documents'] ?? [],
            ),
        ];
    }
}
