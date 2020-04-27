<?php
declare(strict_types=1);
namespace alvin0319\ServerLogManager\form;

use alvin0319\ServerLogManager\ServerLogManager;
use pocketmine\form\Form;
use pocketmine\Player;

class CommandLogSearchForm implements Form{

	protected $logs = [];

	public function jsonSerialize() : array{
		$lang = ServerLogManager::getInstance()->getLang();
		$this->logs = array_values(array_diff(scandir(ServerLogManager::getInstance()->getDataFolder() . "command/"), [".", ".."]));
		return [
			"type" => "custom_form",
			"title" => $lang->getNested("form.searchForm.title"),
			"content" => [
				[
					"type" => "dropdown",
					"text" => $lang->getNested("form.searchForm.dropdown"),
					"options" => $this->logs
				],
				[
					"type" => "input",
					"text" => $lang->getNested("form.searchForm.input")
				]
			]
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if(is_array($data)){
			if(isset($this->logs[$data[0]])){
				if($this->logs[$data[0]] === date("Y-m-d") . ".yml"){
					$logData = ServerLogManager::getInstance()->getCommandLogs();
					if(trim($data[1] ?? "") !== ""){
						$results = [];
						foreach($logData as $datum){
							if($datum["issuer"] === $data[1]){
								$results[] = $datum["date"] . ": " . $datum["command"];
							}
						}
						$player->sendForm(new SearchResultByPlayerForm($results, $data[1], date("Y-m-d"), "command", 1));
					}else{
						$results = [];
						foreach($logData as  $datum){
							$results[] = $datum["date"] . ": " . $datum["command"];
						}
						$player->sendForm(new SearchResultForm($results, date("Y-m-d"), "command"));
					}
				}else{
					$logData = ServerLogManager::getInstance()->getCommandLogsFor($this->logs[$data[0]]);
					if(trim($datum[1] ?? "") !== ""){
						$results = [];
						foreach($logData as $datum){
							if($datum["issuer"] === $data[1]){
								$results[] = $datum["date"] . ": " . $datum["command"];
							}
						}
						$player->sendForm(new SearchResultByPlayerForm($results, $data[1], explode(".", $this->logs[$data[0]])[0], "command", 1));
					}else{
						$results = [];
						foreach($logData as  $datum){
							$results[] = $datum["date"] . ": " . $datum["command"];
						}
						$player->sendForm(new SearchResultForm($results, explode(".", $this->logs[$data[0]])[0], "command"));
					}
				}
			}
		}
	}
}