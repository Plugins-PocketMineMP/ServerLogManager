<?php
declare(strict_types=1);
namespace alvin0319\ServerLogManager\form;

use alvin0319\ServerLogManager\ServerLogManager;
use pocketmine\form\Form;
use pocketmine\Player;

class LogMainForm implements Form{

	public function jsonSerialize() : array{
		$lang = ServerLogManager::getInstance()->getLang();
		return [
			"type" => "form",
			"title" => $lang->getNested("form.main.title", "Log UI"),
			"content" => $lang->getNested("form.main.content", ""),
			"buttons" => [
				["text" => $lang->getNested("form.main.command")],
				["text" => $lang->getNested("form.main.chat")],
				["text" => $lang->getNested("form.main.block")]
			]
		];
	}

	public function handleResponse(Player $player, $data) : void{
		$lang = ServerLogManager::getInstance()->getLang();
		if(is_int($data)){
			switch($data){
				case 0:
					$player->sendForm(new CommandLogSearchForm());
					break;
				case 1:
					$player->sendForm(new ChatLogSearchForm());
					break;
				case 2:
					// block
					$v = ServerLogManager::getInstance()->setBlockLogMode($player);
					$player->sendMessage(ServerLogManager::$prefix . $lang->getNested("message.block-log-" . ($v ? "enabled" : "disabled")));
					break;
			}
		}
	}
}