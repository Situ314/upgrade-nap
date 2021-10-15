<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\OperaHelper;

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
        if ($xml) OperaHelper::sendXmlToAws($xml);
    }
}
