<?php

declare(strict_types=1);

include __DIR__ . "/../libs/profiles.php";
include __DIR__ . "/../libs/buffer.php";


class AmsReader extends IPSModule {
	use Profiles;
	use Buffer;
	
	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

		$this->RegisterPropertyString('MQTTTopic', '');

		$this->RegisterPropertyBoolean('AchiveDailyUsage', true);
		$this->RegisterPropertyBoolean('AchiveMonthlyUsage', true);
		$this->RegisterPropertyBoolean('AchiveYearlyUsage', true);

		$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' hours');
		$this->RegisterProfileIntegerMin('AMSR.RSSI', 'Intensity' , '', ' dBm');
		$this->RegisterProfileFloatEx('AMSR.TemperatureNA', 'Temperature' , '', '', [[-127, 'N/A', '', -1]]);
		
		$this->RegisterVariableString('name', 'Name', '', 0);
		$this->RegisterVariableInteger('up', 'Uptime', 'AMSR.Uptime.' . $this->InstanceID , 1);
		$this->RegisterVariableFloat('vcc', 'Vcc', '~Volt', 2);
		$this->RegisterVariableInteger('rssi', 'RSSI', 'AMSR.RSSI', 3);
		$this->RegisterVariableFloat('temp', 'Temperature', '~Temperature', 4);
		$this->RegisterVariableFloat('P', 'Active Power', '~Power', 5);
		$this->RegisterVariableFloat('MaxPowerToday', 'Todays Max Power', '~Power', 6);
		$this->RegisterVariableFloat('AccHour', 'Accumulated Last Hour', '~Electricity', 7);
		$this->RegisterVariableFloat('AccToday', 'Accumulated Today', '~Electricity', 8);
		$this->RegisterVariableFloat('AccMonth', 'Accumulated Month', '~Electricity', 9);
		$this->RegisterVariableFloat('AccYear', 'Accumulated Year', '~Electricity', 10);
		$this->RegisterVariableFloat('DailyUsage', 'Daily Usage', '~Electricity', 11);
		$this->RegisterVariableFloat('MonthlyUsage', 'Monthly Usage', '~Electricity', 12);
		$this->RegisterVariableFloat('YearlyUsage', 'Yearly Usage', '~Electricity', 13);
		$this->RegisterVariableFloat('tPI', 'Total Usage', '~Electricity', 14);
		$this->RegisterVariableFloat('U1', 'Voltage L1', '~Volt', 15);
		$this->RegisterVariableFloat('I1', 'Current L1', '~Ampere', 16);
		$this->RegisterVariableFloat('U2', 'Voltage L2', '~Volt', 17);
		$this->RegisterVariableFloat('I2', 'Current L2', '~Ampere', 18);
		$this->RegisterVariableFloat('U3', 'Voltage L3', '~Volt', 19);
		$this->RegisterVariableFloat('I3', 'Current L3', '~Ampere', 20);

		$this->RegisterTimer('MidnightTimer', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "Midnight", 0);'); 
		$this->RegisterTimer('SetMidnightTimer', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "SetMidnight", 0);'); 
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

		$this->RegisterMessage(0, IPS_KERNELMESSAGE);

		$archiveModules = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
		if(count($archiveModules)>0) {
			$archiveModuleId = $archiveModules[0];

			$variableId = $this->GetIDForIdent('DailyUsage');
			if($this->ReadPropertyBoolean('AchiveDailyUsage')) {
				AC_SetLoggingStatus($archiveModuleId, $variableId, true);
				AC_SetAggregationType($archiveModuleId, $variableId, 0);
				AC_SetGraphStatus($archiveModuleId, $variableId, true);
			} else {
				AC_SetLoggingStatus($archiveModuleId, $variableId, false);
			}

			$variableId = $this->GetIDForIdent('MonthlyUsage');
			if($this->ReadPropertyBoolean('AchiveMonthlyUsage')) {
				AC_SetLoggingStatus($archiveModuleId, $variableId, true);
				AC_SetAggregationType($archiveModuleId, $variableId, 0);
				AC_SetGraphStatus($archiveModuleId, $variableId, true); 
			} else {
				AC_SetLoggingStatus($archiveModuleId, $variableId, false);
			}

			$variableId = $this->GetIDForIdent('YearlyUsage');
			if($this->ReadPropertyBoolean('AchiveYearlyUsage')) {
				AC_SetLoggingStatus($archiveModuleId, $variableId, true);
				AC_SetAggregationType($archiveModuleId, $variableId, 0);
				AC_SetGraphStatus($archiveModuleId, $variableId, true);
			} else {
				AC_SetLoggingStatus($archiveModuleId, $variableId, false);
			}
		}

		if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->Init();
        }
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

		$this->SendDebug(__FUNCTION__, sprintf('Received a message: %d - %d - %d', $SenderID, $Message, $data[0]), 0);

		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->LogMessage('Detected "Kernel Ready"!', KL_NOTIFY);
			$this->Init();
		}
    }

	private function Init(bool $NewDiscover=true) {
		$msg = 'Initializing...';
		
		$this->LogMessage($msg, KL_NOTIFY);
		$this->SendDebug(__FUNCTION__, $msg, 0);
		
		$this->InitAccumulatedValues();

		$this->SetTimerInterval('MidnightTimer', ($this->SecondsToMidnight()-2)*1000);
	}

	public function RequestAction($Ident, $Value) {
		switch (strtolower($Ident)) {
			case 'midnight':
				$this->SetTimerInterval('SetMidnightTimer', 10000);
				$this->TransferValues();
				break;
			case 'setmidnight':
				$this->SetTimerInterval('SetMidnightTimer', 0);
				$this->SetTimerInterval('MidnightTimer', ($this->SecondsToMidnight()-2)*1000);
				break;
		}
		
	}

	private function TransferValues() {
		if($this->IsLastDayInYear()) {
			$totalNowThisYear = $this->GetValue('AccYear');
			$this->SetValue('YearlyUsage', $totalNowThisYear);
		}

		if($this->IsLastDayInMonth()) {
			$totalNowThisMonth = $this->GetValue('AccMonth');
			$this->SetValue('MonthlyUsage', $totalNowThisMonth);
		}

		$totalNowToday = $this->GetValue('AccToday');
		$this->SetValue('DailyUsage', $totalNowToday);
	}

	private function InitAccumulatedValues() {
		$this->SetBuffer('LastUpdateActivePower',json_encode(hrtime(true)));

		if(!$this->CheckVariableByChangedMonth('AccMonth')) {
			$this->SetValue('AccMonth', 0);
		}
		
		if(!$this->CheckVariableByChangedDay('AccToday')) {
			$this->SetValue('AccToday', 0);
		}

		if(!$this->CheckVariableByChangedHour('AccHour')) {
			$this->SetValue('AccHour', 0);
		}
	}
		
	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		if(isset($data->Payload)) {
			$payload = json_decode($data->Payload);
			if(isset($payload->id)) {
				$this->SendDebug(__FUNCTION__, sprintf('Received data. The data was: %s', $JSONString), 0);	
				$this->HandlePayload($payload);
				return;
			} 
		}
		
		$msg = sprintf('Received invalid data. Missing key "Payload" and/or "Id". Data received was: %s ', $JSONString);
		$this->SendDebug(__FUNCTION__, $msg, 0);
		$this->LogMessage($msg, KL_ERROR);
	}

	private function HandlePayload(object $Payload) {
		$this->SendDebug(__FUNCTION__, 'Analyzing payload...', 0);	
		$this->SendDebug(__FUNCTION__, 'Updating variables...', 0);	
	
		if(isset($Payload->up)) { // ESP device uptime
			$hours = (int)($Payload->up / 3600);
			
			if($hours>23) { 
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' days');
				$this->SetValue('up', (int)($hours/24));
			} else if($hours>0) {
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' hour(s)');
				$this->SetValue('up', $hours);
			} else {
				$this->RegisterProfileIntegerMin('AMSR.Uptime.' . $this->InstanceID, 'Hourglass', '', ' minute(s)');
				$this->SetValue('up', (int)($Payload->up/60));
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

		if(isset($Payload->data->tPI)) { 
			$this->SetValue('tPI', $Payload->data->tPI);
		}
		
		if(isset($Payload->data->P)) { // Active import
			$now = hrtime(true);

			$activePower = $Payload->data->P/1000;
			$this->SetValue('P', $activePower);
			
			$lastUpdateActivePower = json_decode($this->GetBuffer('LastUpdateActivePower'));
			
			if($lastUpdateActivePower!=0) {
				$diff = ($now-$lastUpdateActivePower)*pow(10, -9)/3600;
				$deltaUsage = $diff*$activePower;

				$totalNowThisYear = $this->GetValue('AccYear');
				if($this->CheckVariableByChangedYear('AccYear')) {
					$newTotal = $totalNowThisYear + $deltaUsage;
					$this->SetValue('AccYear', $newTotal);
				} else {
					$this->SetValue('AccYear', $deltaUsage);
				}
				
				$totalNowThisMonth = $this->GetValue('AccMonth');
				if($this->CheckVariableByChangedMonth('AccMonth')) {
					$newTotal = $totalNowThisMonth + $deltaUsage;
					$this->SetValue('AccMonth', $newTotal);
				} else {
					//$this->SetValue('MonthlyUsage', $totalNowThisMonth);
					$this->SetValue('AccMonth', $deltaUsage);
				}

				$totalNowToday = $this->GetValue('AccToday');
				if($this->CheckVariableByChangedDay('AccToday')) {
					$newTotal = $totalNowToday + $deltaUsage;
					$this->SetValue('AccToday', $newTotal);

					$currentMaxPower = $this->GetValue('MaxPowerToday');
					if($activePower>$currentMaxPower) {
						$this->SetValue('MaxPowerToday', $activePower);
					}
				} else {
					//$this->SetValue('DailyUsage', $totalNowToday);
					$this->SetValue('AccToday', $deltaUsage);
					$this->SetValue('MaxPowerToday', $activePower);
				}
			
				if($this->CheckVariableByChangedHour('AccHour')) {
					$totalNow = $this->GetValue('AccHour');
					$newTotal = $totalNow + $deltaUsage;
					$this->SetValue('AccHour', $newTotal);
				} else {
					$this->SetValue('AccHour', $deltaUsage);
				}
			}

			$this->SetBuffer('LastUpdateActivePower', json_encode($now));
		}

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

		$this->SendDebug(__FUNCTION__, 'Completed analyzing payload', 0);	
	}

	private function GetHour() {
		$now = new DateTime('now');
			
		return (int)$now->Format('H');
	}

	private function SecondsToMidnight() {
		$now = new DateTime('now');
		$offset = timezone_offset_get($now->getTimezone(), $now);
		return 86400-(time()+$offset)%86400;
	}

	private function SecondsToNextHour() {
		$now = new DateTime('now');
		$offset = timezone_offset_get($now->getTimezone(), $now);
		return 3600-(time()+$offset)%3600;
	}

	private function IsLastDayInMonth() {
		$now = new DateTime('now');
		$thisDay = (int)$now->format('d');
		$lastDay = (int)$now->modify('last day of')->format('d');

		return $thisDay==$lastDay;
	}

	private function IsLastDayInYear() {
		$now = new DateTime('now');
		$thisDay = $now->format('d-m-Y');
		$lastDay = $now->modify('last day of december this year')->format('d-m-Y');
	
		return $thisDay==$lastDay;
	}

	private function CheckVariableByChangedYear($Ident) {
		$lastChanged = $this->GetVariableChanged($Ident);
        $now = new DateTime('now');
                		               
        return $now->format('Y')==$lastChanged->format('Y');
    }

	private function CheckVariableByChangedMonth($Ident) {
		$lastChanged = $this->GetVariableChanged($Ident);
        $now = new DateTime('now');
                		               
        return $now->format('Ym')==$lastChanged->format('Ym');
    }

	private function CheckVariableByChangedDay($Ident) {
		$lastChanged = $this->GetVariableChanged($Ident);
        $now = new DateTime('now');
                		               
        return $now->format('Ymd')==$lastChanged->format('Ymd');
    }

	private function CheckVariableByChangedHour($Ident) {
		$lastChanged = $this->GetVariableChanged($Ident);
        $now = new DateTime('now');
                		               
        return $now->format('YmdH')==$lastChanged->format('YmdH');
    }

	private function GetVariableChanged(string $Ident) {
		$variable = IPS_GetVariable($this->GetIDForIdent($Ident));
		
		$dt = new DateTime();
		$dt->setTimestamp($variable['VariableChanged']);

		return $dt;
	}
}