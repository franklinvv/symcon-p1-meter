<?php
	define("BUFFER", "P1 Telegram");

	class P1Meter extends IPSModule {

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
			$this->RegisterPropertyBoolean("Track power generation", false);

			$var = IPS_GetObjectIDByIdent("CurrentPowerConsumption", $this->InstanceID);
			if(!$var) {
				$id = $this->RegisterVariableFloat("CurrentPowerConsumption", "Power consumption", "~Watt.14490", 0);
				$this->enableLogging($id, 0);
			}

			$var = IPS_GetObjectIDByIdent("ConsumedElectricityHigh", $this->InstanceID);
			if(!$var) {
				$id = $this->RegisterVariableFloat("ConsumedElectricityHigh", "Consumed electricity (high)", "~Electricity", 2);
				$this->enableLogging($id, 1);
			}

			$var = IPS_GetObjectIDByIdent("ConsumedElectricityLow", $this->InstanceID);
			if(!$var) {
				$id = $this->RegisterVariableFloat("ConsumedElectricityLow", "Consumed electricity (low)", "~Electricity", 1);
				$this->enableLogging($id, 1);
			}

			$var = IPS_GetObjectIDByIdent("ConsumedGas", $this->InstanceID);
			if(!$var) {
				$id = $this->RegisterVariableFloat("ConsumedGas", "Consumed gas", "~Gas", 10);
				$this->enableLogging($id, 1);
			}
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();
			$this->SetBuffer(BUFFER, "");

			$trackPowerGeneration = $this->ReadPropertyBoolean("Track power generation");
			if($trackPowerGeneration) {
				$var = IPS_GetObjectIDByIdent("CurrentPowerGeneration", $this->InstanceID);
				if(!$var) {
					$id = $this->RegisterVariableFloat("CurrentPowerGeneration", "Power generation", "~Watt.14490", 3);
					$this->enableLogging($id, 0);
				}
				
				$var = IPS_GetObjectIDByIdent("GeneratedElectricityHigh", $this->InstanceID);
				if(!$var) {
					$id = $this->RegisterVariableFloat("GeneratedElectricityHigh", "Generated electricity (high)", "~Electricity", 5);
					$this->enableLogging($id, 1);
				}

				$var = IPS_GetObjectIDByIdent("GeneratedElectricityLow", $this->InstanceID);
				if(!$var) {
					$id = $this->RegisterVariableFloat("GeneratedElectricityLow", "Generated electricity (low)", "~Electricity", 4);
					$this->enableLogging($id, 1);
				}
			} else {
				$this->UnregisterVariable("CurrentPowerGeneration");
				$this->UnregisterVariable("GeneratedElectricityHigh");
				$this->UnregisterVariable("GeneratedElectricityLow");
			}
		}

		private function enableLogging($id, $aggregationType) {
			$instances = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}");

			if(!AC_GetLoggingStatus($instances[0], $id)) {
				AC_SetLoggingStatus($instances[0], $id, true);
			}
			if(AC_GetAggregationType($instances[0], $id) != $aggregationType) {
				AC_SetAggregationType($instances[0], $id, $aggregationType);
			}
		}

		public function ReceiveData($JSONString) {
			$data = json_decode($JSONString);
			$telegramPart = utf8_decode($data->Buffer);
			if($this->telegram = $this->buildTelegram($telegramPart)) {
				$powerConsumption = $this->extractPowerConsumption();
				if($powerConsumption != $this->GetValue("CurrentPowerConsumption")) {
					$this->SetValue("CurrentPowerConsumption", $powerConsumption);
				}
				//$this->UpdateFormField("Current power consumption", "caption", sprintf("Current power consumption: %.0f Watt", $powerConsumption));
				
				$consumedHigh = round($this->extractConsumedHigh(), 2);
				if($consumedHigh != $this->GetValue("ConsumedElectricityHigh")) {
					$this->SetValue("ConsumedElectricityHigh", $consumedHigh);
				}

				$consumedLow = round($this->extractConsumedLow(), 2);
				if($consumedLow != $this->GetValue("ConsumedElectricityLow")) {
					$this->SetValue("ConsumedElectricityLow", $consumedLow);
				}

				$consumedGas = round($this->extractConsumedGas(), 2);
				if($consumedGas != $this->GetValue("ConsumedGas")) {
					$this->SetValue("ConsumedGas", $consumedGas);
				}

				$trackPowerGeneration = $this->ReadPropertyBoolean("Track power generation");
				if($trackPowerGeneration) {
					$powerGeneration = $this->extractPowerGeneration();
					if($powerGeneration != $this->GetValue("CurrentPowerGeneration")) {
						$this->SetValue("CurrentPowerGeneration", $powerGeneration);
					}
					//$this->UpdateFormField("Current power generation", "caption", sprintf("Current power generation: %.0f Watt", $powerGeneration));

					$generatedHigh = $this->extractGeneratedHigh();
					if($generatedHigh != $this->GetValue("GeneratedElectricityHigh")) {
						$this->SetValue("GeneratedElectricityHigh", $generatedHigh);
					}

					$generatedLow = $this->extractGeneratedLow();
					if($generatedLow != $this->GetValue("GeneratedElectricityLow")) {
						$this->SetValue("GeneratedElectricityLow", $generatedLow);
					}
				}
			}
		}

		private function extractCurrentTariff() {
			preg_match("/(?<=0-0:96\.14\.0\()\d+/", $this->telegram, $matches);
			return (int)$matches[0];
		}

		private function extractPowerConsumption() {
			preg_match("/(?<=1-0:1\.7\.0\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0] * 1000;
		}

		private function extractPowerGeneration() {
			preg_match("/(?<=1-0:2\.7\.0\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0] * 1000;
		}

		private function extractConsumedLow() {
			preg_match("/(?<=1-0:1\.8\.1\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0];
		}

		private function extractConsumedHigh() {
			preg_match("/(?<=1-0:1\.8\.2\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0];
		}

		private function extractGeneratedLow() {
			preg_match("/(?<=1-0:2\.8\.1\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0];
		}

		private function extractGeneratedHigh() {
			preg_match("/(?<=1-0:2\.8\.2\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0];
		}

		private function extractConsumedGas() {
			preg_match("/(?<=0-1:24.2.1\(\d{12}W|S\)\()\d+\.\d+/", $this->telegram, $matches);
			return (float)$matches[0];
		}

		private function buildTelegram($part) {
			$parts = $this->GetBuffer(BUFFER).$part;
			if(strpos($parts, "!")) {
				$this->SetBuffer(BUFFER, "");
				return $parts;
			}
			$this->SetBuffer(BUFFER, $parts);
			return NULL;
		}
	}