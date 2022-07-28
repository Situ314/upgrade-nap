<?php

namespace App\Jobs;

use App\Helpers\OperaHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OperaProcessProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $resort_id;

    private $unique_id;

    public function __construct($resort_id, $unique_id)
    {
        $this->resort_id = $resort_id;
        $this->unique_id = $unique_id;
    }

    public function handle()
    {
        $xml = OperaHelper::getProfileData($this->resort_id, $this->unique_id);
        \Log::info($xml);
        if ($xml) {
            OperaHelper::sendXmlToAws($xml, $this->resort_id);
        }
    }
}
