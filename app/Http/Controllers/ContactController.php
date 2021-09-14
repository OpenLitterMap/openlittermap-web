<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\Contact;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{

    public function __invoke(ContactRequest $request)
    {
        Mail::to('info@openlittermap.com')
            ->send(new Contact(
                $request->subject,
                $request->message,
                $request->name,
                $request->email
            ));
    }
}
