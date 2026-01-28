<?php
	define("BUFFER", "P1 Telegram");

	class P1Meter extends IPSModule {

		private ?string $telegram = null;

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
			$this->RegisterPropertyBoolean("Track power generation", false);

			$var = $this->getVariableIdByIdent("CurrentPowerConsumption");
			if ($var === false) {
				$id = $this->RegisterVariableFloat("CurrentPowerConsumption", "Power consumption", "~Watt.14490", 0);
				$this->enableLogging($id, 0);
			}

			$var = $this->getVariableIdByIdent("ConsumedElectricityHigh");
			if ($var === false) {
				$id = $this->RegisterVariableFloat("ConsumedElectricityHigh", "Consumed electricity (high)", "~Electricity", 2);
				$this->enableLogging($id, 1);
			}

			$var = $this->getVariableIdByIdent("ConsumedElectricityLow");
			if ($var === false) {
				$id = $this->RegisterVariableFloat("ConsumedElectricityLow", "Consumed electricity (low)", "~Electricity", 1);
				$this->enableLogging($id, 1);
			}

			$var = $this->getVariableIdByIdent("ConsumedGas");
			if ($var === false) {
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
				$var = $this->getVariableIdByIdent("CurrentPowerGeneration");
				if ($var === false) {
					$id = $this->RegisterVariableFloat("CurrentPowerGeneration", "Power generation", "~Watt.14490", 3);
					$this->enableLogging($id, 0);
				}
				
				$var = $this->getVariableIdByIdent("GeneratedElectricityHigh");
				if ($var === false) {
					$id = $this->RegisterVariableFloat("GeneratedElectricityHigh", "Generated electricity (high)", "~Electricity", 5);
					$this->enableLogging($id, 1);
				}

				$var = $this->getVariableIdByIdent("GeneratedElectricityLow");
				if ($var === false) {
					$id = $this->RegisterVariableFloat("GeneratedElectricityLow", "Generated electricity (low)", "~Electricity", 4);
					$this->enableLogging($id, 1);
				}
			} else {
				$this->UnregisterVariable("CurrentPowerGeneration");
				$this->UnregisterVariable("GeneratedElectricityHigh");
				$this->UnregisterVariable("GeneratedElectricityLow");
			}
		}

		private function getVariableIdByIdent(string $ident): int|false {
			try {
				return IPS_GetObjectIDByIdent($ident, $this->InstanceID);
			} catch (Exception $e) {
				return false;
			}
		}

		private function enableLogging($id, $aggregationType) {
			$instances = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}");

			if(!AC_GetLoggingStatus($instances[0], $id)) {
				AC_SetLoggingStatus($instances[0], $id, true);
			}
			if(AC_GetAggregationType($instances[0], $id) != $aggregationType) {
				AC_SetAggregationType($instances[0], $id, $aggregationType);
				AC_SetCounterIgnoreZeros($instances[0], $id, true);
			}
		}

		public function ReceiveData($JSONString) {
			$data = json_decode($JSONString);
			$telegramPart = utf8_decode($data->Buffer);
			$this->telegram = $this->buildTelegram($telegramPart);

			if ($this->telegram === null) {
				return;
			}

			$trackPowerGeneration = $this->ReadPropertyBoolean("Track power generation");

			if (!$this->validateTelegram($trackPowerGeneration)) {
				$this->SendDebug("P1 Error", "Invalid telegram received, skipping", 0);
				return;
			}

			$powerConsumption = $this->extractPowerConsumption();
			if ($powerConsumption !== null && $powerConsumption != $this->GetValue("CurrentPowerConsumption")) {
				$this->SetValue("CurrentPowerConsumption", $powerConsumption);
			}

			$consumedHigh = $this->extractConsumedHigh();
			if ($consumedHigh !== null && round($consumedHigh, 2) > $this->GetValue("ConsumedElectricityHigh")) {
				$this->SetValue("ConsumedElectricityHigh", round($consumedHigh, 2));
			}

			$consumedLow = $this->extractConsumedLow();
			if ($consumedLow !== null && round($consumedLow, 2) > $this->GetValue("ConsumedElectricityLow")) {
				$this->SetValue("ConsumedElectricityLow", round($consumedLow, 2));
			}

			$consumedGas = $this->extractConsumedGas();
			if ($consumedGas !== null && round($consumedGas, 2) > $this->GetValue("ConsumedGas")) {
				$this->SetValue("ConsumedGas", round($consumedGas, 2));
			}

			if ($trackPowerGeneration) {
				$powerGeneration = $this->extractPowerGeneration();
				if ($powerGeneration !== null && $powerGeneration != $this->GetValue("CurrentPowerGeneration")) {
					$this->SetValue("CurrentPowerGeneration", $powerGeneration);
				}

				$generatedHigh = $this->extractGeneratedHigh();
				if ($generatedHigh !== null && $generatedHigh != $this->GetValue("GeneratedElectricityHigh")) {
					$this->SetValue("GeneratedElectricityHigh", $generatedHigh);
				}

				$generatedLow = $this->extractGeneratedLow();
				if ($generatedLow !== null && $generatedLow != $this->GetValue("GeneratedElectricityLow")) {
					$this->SetValue("GeneratedElectricityLow", $generatedLow);
				}
			}
		}

		private function extractValue(string $pattern, float $multiplier = 1.0): ?float {
			if (preg_match($pattern, $this->telegram, $matches) !== 1) {
				return null;
			}
			return (float)$matches[0] * $multiplier;
		}

		private function validateTelegram(bool $includeGeneration = false): bool {
			$requiredPatterns = [
				'power_consumption' => "/1-0:1\.7\.0\(\d+\.\d+/",
				'consumed_high'     => "/1-0:1\.8\.2\(\d+\.\d+/",
				'consumed_low'      => "/1-0:1\.8\.1\(\d+\.\d+/",
				'consumed_gas'      => "/0-1:24\.2\.1\(\d{12}(W|S)\)\(\d+\.\d+/",
			];

			if ($includeGeneration) {
				$requiredPatterns['power_generation'] = "/1-0:2\.7\.0\(\d+\.\d+/";
				$requiredPatterns['generated_high']   = "/1-0:2\.8\.2\(\d+\.\d+/";
				$requiredPatterns['generated_low']    = "/1-0:2\.8\.1\(\d+\.\d+/";
			}

			foreach ($requiredPatterns as $field => $pattern) {
				if (preg_match($pattern, $this->telegram) !== 1) {
					$this->SendDebug("P1 Validation", "Missing required field: $field", 0);
					return false;
				}
			}
			return true;
		}

		private function extractCurrentTariff(): ?int {
			if (preg_match("/(?<=0-0:96\.14\.0\()\d+/", $this->telegram, $matches) !== 1) {
				return null;
			}
			return (int)$matches[0];
		}

		private function extractPowerConsumption(): ?float {
			return $this->extractValue("/(?<=1-0:1\.7\.0\()\d+\.\d+/", 1000);
		}

		private function extractPowerGeneration(): ?float {
			return $this->extractValue("/(?<=1-0:2\.7\.0\()\d+\.\d+/", 1000);
		}

		private function extractConsumedLow(): ?float {
			return $this->extractValue("/(?<=1-0:1\.8\.1\()\d+\.\d+/");
		}

		private function extractConsumedHigh(): ?float {
			return $this->extractValue("/(?<=1-0:1\.8\.2\()\d+\.\d+/");
		}

		private function extractGeneratedLow(): ?float {
			return $this->extractValue("/(?<=1-0:2\.8\.1\()\d+\.\d+/");
		}

		private function extractGeneratedHigh(): ?float {
			return $this->extractValue("/(?<=1-0:2\.8\.2\()\d+\.\d+/");
		}

		private function extractConsumedGas(): ?float {
			return $this->extractValue("/(?<=0-1:24.2.1\(\d{12}(W|S)\)\()\d+\.\d+/");
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