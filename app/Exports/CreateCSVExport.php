<?php

namespace App\Exports;

use App\Models\Photo;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CreateCSVExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;

    /**
     * Init args
     */
    public function __construct ($email)
    {
        $this->email = $email;
    }

    /**
     * Define column titles
     */
    public function headings(): array
    {
        return [
            "id"
        ];
    }

    /**
     * Map over query response
     * This will insert the each row under each heading
     */
    public function map ($row): array
    {
        return [
            $row->id
        ];
    }

    /**
     * Create a query which we will loop over in the map function
     * no need to use ->get();
     */
    public function query ()
    {
        return Photo::where('id', 1);
    }
}
