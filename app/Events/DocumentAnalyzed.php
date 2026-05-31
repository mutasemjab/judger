<?php

namespace App\Events;

use App\Models\CaseDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentAnalyzed
{
    use Dispatchable, SerializesModels;

    public function __construct(public CaseDocument $document) {}
}
