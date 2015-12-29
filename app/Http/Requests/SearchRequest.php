<?php

namespace FalconSearch\Http\Requests;

class SearchRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'q' => 'string'
        ];
    }

    public function getQuery()
    {
        return $this->get('q', '');
    }

    public function getPage()
    {
        return $this->get('page', 1);
    }
}
