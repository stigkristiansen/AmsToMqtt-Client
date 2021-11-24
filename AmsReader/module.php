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
        	$this->SendDebug(__FUNCTION__, $JSONString, 0);
		}
	}