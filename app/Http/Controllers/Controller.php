<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** The signed-in staff account (set by the `staff` middleware), as an array. */
    protected function staff(): array
    {
        return request()->attributes->get('staff') ?? current_staff() ?? [];
    }

    /** The request body: JSON for the API endpoints, form fields otherwise. */
    protected function input(): array
    {
        $request = request();
        return $request->isJson() ? (array) $request->json()->all() : $request->all();
    }
}
