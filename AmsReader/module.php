<?php

declare(strict_types=1);

include __DIR__ . "/../libs/profiles.php";

class AmsReader extends IPSModule {
	use Profiles;
	
	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

		$this->RegisterPropertyString('MQTTTopic', '');

		$this->RegisterProfileIntegerMin('AMSR.Hours.' . $this->InstanceID, 'Hourglass', '', ' hours');
		$this->RegisterProfileIntegerMin('AMSR.RSSI', 'Intensity' , '', ' dBm');
		
		$this->RegisterVariableString('name', 'Name', '', 0);
		$this->RegisterVariableInteger('up', 'Uptime', 'AMSR.Hours.' . $this->InstanceID , 1);
		$this->RegisterVariableFloat('vcc', 'VCC', '~Volt', 2);
		$this->RegisterVariableInteger('rssi', 'RSSI', 'AMSR.RSSI', 3);
		$this->RegisterVariableFloat('temp', 'Temperature', '~Temperature', 4);
		$this->RegisterVariableFloat('P', 'Active Power', '~Power', 5);
		$this->RegisterVariableFloat('tPI', 'Total usage', '~Electricity', 6);
		$this->RegisterVariableFloat('U1', 'Voltage L1', '~Volt', 7);
		$this->RegisterVariableFloat('I1', 'Current L1', '~Ampere', 8);
		$this->RegisterVariableFloat('U2', 'Voltage L2', '~Volt', 9);
		$this->RegisterVariableFloat('I2', 'Current L2', '~Ampere', 10);
		$this->RegisterVariableFloat('U3', 'Voltage L3', '~Volt', 11);
		$this->RegisterVariableFloat('I3', 'Current L3', '~Ampere', 12);
	}

	public function Destroy() {
		$this->DeleteProfile('AMSR.Hours.' . $this->InstanceID);	

		$module = json_decode(file_get_contents(__DIR__ . '/module.json'));
		if(count(IPS_GetInstanceListByModuleID($module->id))==0) {
			$this->DeleteProfile('AMSR.RSSI');
		}	
		
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
		if(isset($data->Payload) && isset($data->Payload->data)) {
			$this->SendDebug(__FUNCTION__, sprintf('Received data. The data was: %s', json_encode($data->Payload)), 0);	
			$this->HandlePayload($data->Payload);
		} else {
			$msg = sprintf('Received invalid data. Missing key "Payload" and/or "data". Data received was: %s ', $JSONString);
			$this->SendDebug(__FUNCTION__, $msg, 0);
			$this->LogMessage($msg, KL_ERROR);
		}
	}

	protected function HandlePayload(object $Payload) {
		$this->SendDebug(__FUNCTION__, 'Analyzing payload...', 0);	
		$this->SendDebug(__FUNCTION__, 'Updating variables...', 0);	
	
		if(isset($Payload->up)) { // ESP device uptime
			$hours = $Payload->up % 3600;
			if($hours>0) {
				$this->RegisterProfileIntegerMin('AMSR.Hours.' . $this->InstanceID, 'Hourglass', '', ' hours');
				$this->SetValue('up', $hours);
			} else {
				$this->RegisterProfileIntegerMin('AMSR.Hours.' . $this->InstanceID, 'Hourglass', '', ' minutes');
				$this->SetValue('up', $Payload->up % 60);
			}
		}

		if(isset($Payload->name)) { // ESP device name
			$this->SetValue('name', $Payload->name);
		}

		if(isset($Payload->vcc)) { // ESP device voltage
			$this->SetValue('vcc', $Payload->vcc);
		}

		if(isset($Payload->rssi)) { // ESP device WiFi signal strength
			$this->SetValue('rssi', $Payload->rssi);
		}

		if(isset($Payload->temp)) { // ESP device temperature
			$this->SetValue('temp', $Payload->temp);
		}
		
		if(isset($Payload->data->P)) { // Active import
			$this->SetValue('P', $Payload->P);
		}
/*		
		if(isset($Payload->data->Q)) { // Reactive import
			$this->SetValue('Q', $Payload->Q);
		}		

		if(isset($Payload->data->PO)) { //  Active export
			$this->SetValue('PO', $Payload->PO);
		}		

		if(isset($Payload->data->QO)) { // Reactive export
			$this->SetValue('QO', $Payload->QO);
		}		
*/
		if(isset($Payload->data->I1)) { // L1 current
			$this->SetValue('I1', $Payload->I1);
		}		

		if(isset($Payload->data->I2)) { // L2 current
			$this->SetValue('I2', $Payload->I2);
		}		

		if(isset($Payload->data->L3)) { // L3 current
			$this->SetValue('L3', $Payload->L3);
		}		

		if(isset($Payload->data->U1)) { // L1 voltage
			$this->SetValue('U1', $Payload->U1);
		}		

		if(isset($Payload->data->U2)) { // L2 voltage
			$this->SetValue('U2', $Payload->U2);
		}		

		if(isset($Payload->data->U3)) { //  L3 voltage
			$this->SetValue('U3', $Payload->U3);
		}		

		if(isset($Payload->data->tPI)) { // Hourly accumulated active import
			$this->SetValue('tPI', $Payload->tPI);
		}		
/*
		if(isset($Payload->data->tPO)) { // Hourly accumulated active export
			$this->SetValue('tPO', $Payload->tPO);
		}		

		if(isset($Payload->data->tQI)) { // Hourly accumulated reactive import
			$this->SetValue('tQI', $Payload->tQI);
		}		

		if(isset($Payload->data->tQO)) { // Hourly accumulated reactive export
			$this->SetValue('tQO', $Payload->tQO);
		}		
*/
		$this->SendDebug(__FUNCTION__, 'Completed analyzing payload', 0);	
	}
}