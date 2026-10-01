<?php

namespace App\Support;

class BidderRegistrationRequirements
{
    public const FILE_EXTENSIONS = 'pdf,jpg,jpeg,png';

    public const DOCUMENTS = [
        'business_permit' => [
            'label' => 'Valid Business Permit',
            'document_type' => 'Business Permit',
            'required' => true,
        ],
        'registration_certificate' => [
            'label' => 'DTI/SEC/CDA Registration Certificate',
            'document_type' => 'DTI/SEC Registration',
            'required' => true,
        ],
        'bir_certificate' => [
            'label' => 'BIR Certificate of Registration',
            'document_type' => 'BIR Certificate of Registration',
            'required' => true,
        ],
        'mayors_permit' => [
            'label' => "Mayor's Permit",
            'document_type' => "Mayor's Permit",
            'required' => true,
        ],
        'philgeps_registration' => [
            'label' => 'PhilGEPS Registration',
            'document_type' => 'PhilGEPS Certificate',
            'required' => true,
        ],
        'tax_clearance' => [
            'label' => 'Tax Clearance',
            'document_type' => 'Tax Clearance',
            'required' => true,
        ],
        'omnibus_sworn_statement' => [
            'label' => 'Omnibus Sworn Statement',
            'document_type' => 'Omnibus Sworn Statement',
            'required' => true,
        ],
        'authorized_representative_id' => [
            'label' => 'Authorized Representative Valid ID',
            'document_type' => 'Authorized Representative Valid ID',
            'required' => true,
        ],
        'other_supporting_documents' => [
            'label' => 'Other supporting documents required by BAC',
            'document_type' => 'Other Supporting Documents',
            'required' => false,
        ],
    ];

    public static function documents(): array
    {
        return self::DOCUMENTS;
    }

    public static function requiredDocumentKeys(): array
    {
        return array_keys(array_filter(
            self::DOCUMENTS,
            fn (array $document): bool => (bool) ($document['required'] ?? false)
        ));
    }

    public static function documentTypes(): array
    {
        return array_values(array_map(
            fn (array $document): string => $document['document_type'],
            self::DOCUMENTS
        ));
    }
}
