<?php

declare(strict_types=1);

trait Buffer {
	private function Lock(string $Name) {
		//$this->SendDebug(__FUNCTION__, sprintf('Locking "%s"...',$Name), 0);
        for ($i = 0; $i < 100; $i++){
            if (IPS_SemaphoreEnter(sprintf('%s%s',(string)$this->InstanceID,$Name), 1)){
				//$this->SendDebug(__FUNCTION__, sprintf('"%s" is locked',$Name), 0);
                return true;
            } else {
                //$this->SendDebug(__FUNCTION__, 'Waiting for lock...', 0);
				IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function Unlock(string $Name) {
        IPS_SemaphoreLeave(sprintf('%s%s',(string)$this->InstanceID,$Name));
		//$this->SendDebug(__FUNCTION__, sprintf('Unlocked "%s"', $Name), 0);
    }

    private function UpdateBuffer(string $Name, $Value) {
		$this->UpdateBufferRaw($Name, json_encode($Value));
	}

	private function FetchBuffer(string $Name) {
		$value = $this->FetchBufferRaw($Name);
		return json_decode($value);
	}

	private function FetchBufferRaw(string $Name) {
		if($this->Lock($Name)) {
			$value = $this->GetBuffer($Name);
			//$this->SendDebug(__FUNCTION__, sprintf('Fetched "%s"',$Name), 0);
			$this->Unlock($Name);
			return $value;
		} else {
			$msg = sprintf('Failed to Fetch "%s"',$Name);
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug(__FUNCTION__, $msg, 0);
			return false;
		}
	}

	private function UpdateBufferRaw(string $Name, $Value) {
		if($this->Lock($Name)) {
			$this->SetBuffer($Name, $Value);
			//$this->SendDebug(__FUNCTION__, sprintf('Updated "%s"',$Name), 0);
			$this->Unlock($Name);
		} else {
			$msg = sprintf('Failed to Update "%s"',$Name);
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug(__FUNCTION__, $msg, 0);
		}
	}
}