<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CprScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Pagination requests don't supply folder_path — it comes from session.
        if ($this->isPagination()) {
            return [
                'page'     => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|in:10,20,30',
            ];
        }

        return [
            'folder_path'  => 'required|string|max:500',
            'force_rescan' => 'nullable|boolean',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|in:10,20,30',
            'filter_status' => 'nullable|string|in:Valid,Expiring Soon,Expired,Unknown'
        ];
    }

    /**
     * A "pagination" request is one where the user is paginating existing
     * results (no new scan). These come from the rows-per-page or page-number
     * controls and carry page/per_page but NOT folder_path.
     */
    public function isPagination(): bool
    {
        return ($this->has('page') || $this->has('per_page'))
            && !$this->has('folder_path');
    }
}