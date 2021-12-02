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

		$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' hours');
		$this->RegisterProfileIntegerMin('AMSR.RSSI', 'Intensity' , '', ' dBm');
		$this->RegisterProfileFloatEx('AMSR.TemperatureNA', 'Temperature' , '', '', [[-127, 'N/A', '', -1]]);
		
		$this->RegisterVariableString('name', 'Name', '', 0);
		$this->RegisterVariableInteger('up', 'Uptime', 'AMSR.Uptime.' . $this->InstanceID , 1);
		$this->RegisterVariableFloat('vcc', 'Vcc', '~Volt', 2);
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
		$this->DeleteProfile('AMSR.Uptime.' . $this->InstanceID);	

		$module = json_decode(file_get_contents(__DIR__ . '/module.json'));
		if(count(IPS_GetInstanceListByModuleID($module->id))==0) {
			$this->DeleteProfile('AMSR.RSSI');
			$this->DeleteProfile('AMSR.TemperatureNA');
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
		if(isset($data->Payload)) {
			$payload = json_decode($data->Payload);
			if(isset($payload->data)) {
				$this->SendDebug(__FUNCTION__, sprintf('Received data. The data was: %s', $JSONString), 0);	
				$this->HandlePayload($payload);
				return;
			} 
		}
		
		$msg = sprintf('Received invalid data. Missing key "Payload" and/or "Data". Data received was: %s ', $JSONString);
		$this->SendDebug(__FUNCTION__, $msg, 0);
		$this->LogMessage($msg, KL_ERROR);
		
	}

	protected function HandlePayload(object $Payload) {
		$this->SendDebug(__FUNCTION__, 'Analyzing payload...', 0);	
		$this->SendDebug(__FUNCTION__, 'Updating variables...', 0);	
	
		if(isset($Payload->up)) { // ESP device uptime
			$hours = (int)($Payload->up / 3600);
			
			if($hours>23) { 
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' days');
				$this->SetValue('up', (int)($hours / 24));
			} else if($hours>0) {
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' hours');
				$this->SetValue('up', $hours);
			} else {
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' minutes');
				$this->SetValue('up', (int)($Payload->up / 60));
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
			if($Payload->temp==-127) {
				$this->RegisterVariableFloat('temp', 'Temperature', 'AMSR.TemperatureNA', 4);
			} else {
				$this->RegisterVariableFloat('temp', 'Temperature', '~Temperature', 4);
			}
			
			$this->SetValue('temp', $Payload->temp);
		}
		
		if(isset($Payload->data->P)) { // Active import
			$this->SetValue('P', $Payload->data->P / 1000);
		}
/*		
		if(isset($Payload->data->Q)) { // Reactive import
			$this->SetValue('Q', $Payload->data->Q);
		}		

		if(isset($Payload->data->PO)) { //  Active export
			$this->SetValue('PO', $Payload->data->PO);
		}		

		if(isset($Payload->data->QO)) { // Reactive export
			$this->SetValue('QO', $Payload->data->QO);
		}		
*/
		if(isset($Payload->data->I1)) { // L1 current
			$this->SetValue('I1', $Payload->data->I1);
		}		

		if(isset($Payload->data->I2)) { // L2 current
			$this->SetValue('I2', $Payload->data->I2);
		}		

		if(isset($Payload->data->I3)) { // L3 current
			$this->SetValue('I3', $Payload->data->I3);
		}		

		if(isset($Payload->data->U1)) { // L1 voltage
			$this->SetValue('U1', $Payload->data->U1);
		}		

		if(isset($Payload->data->U2)) { // L2 voltage
			$this->SetValue('U2', $Payload->data->U2);
		}		

		if(isset($Payload->data->U3)) { //  L3 voltage
			$this->SetValue('U3', $Payload->data->U3);
		}		

		if(isset($Payload->data->tPI)) { // Hourly accumulated active import
			$this->SetValue('tPI', $Payload->data->tPI);
		}		
/*
		if(isset($Payload->data->tPO)) { // Hourly accumulated active export
			$this->SetValue('tPO', $Payload->data->tPO);
		}		

		if(isset($Payload->data->tQI)) { // Hourly accumulated reactive import
			$this->SetValue('tQI', $Payload->data->tQI);
		}		

		if(isset($Payload->data->tQO)) { // Hourly accumulated reactive export
			$this->SetValue('tQO', $Payload->data->tQO);
		}		
*/
		$this->SendDebug(__FUNCTION__, 'Completed analyzing payload', 0);	
	}
}