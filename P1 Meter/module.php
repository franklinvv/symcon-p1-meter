<?php
	class P1Meter extends IPSModule {

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
			$this->RegisterPropertyBoolean("Track power generation", false);
		}

		public function Destroy() {
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();
		}

		// public function Send(string $Text)
		// {
		// 	$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Text)));
		// }

		public function ReceiveData($JSONString) {
			$data = json_decode($JSONString);
			$telegram = utf8_decode($data->Buffer);
			

			//IPS_LogMessage("Device RECV", utf8_decode($data->Buffer));
		}
	}