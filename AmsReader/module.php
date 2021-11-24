<?php

declare(strict_types=1);
	class AmsReader extends IPSModule
	{
		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

			$this->RegisterPropertyString('MQTTTopic', '');
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();

			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
						
			$this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('MQTTTopic') . '".*');
		}

		public function ReceiveData($JSONString) {
			$data = json_decode($JSONString);
			if(isset($data->Payload)) {
				$this->SendDebug(__FUNCTION__, sprintf('Received data. The data was: %s', json_encode($data->Payload)), 0);	
			} else {
				$msg = sprintf('Received invalid data. Missing key "Payload". Data received was: %s ', $JSONString);
        		$this->SendDebug(__FUNCTION__, $msg, 0);
				$this-LogMessage($msg, KL_ERROR);
			}
		}
	}