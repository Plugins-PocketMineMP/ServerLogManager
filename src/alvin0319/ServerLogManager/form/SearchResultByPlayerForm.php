<?php
declare(strict_types=1);
namespace alvin0319\ServerLogManager\form;

use alvin0319\ServerLogManager\ServerLogManager;
use pocketmine\form\Form;
use pocketmine\Player;

class SearchResultByPlayerForm implements Form{

	protected $results = [];

	protected $name;

	protected $date;

	protected $type;

	protected $page;

	protected $max;

	public function __construct(array $results, string $name, string $date, string $type, int $page){
		$this->results = $results;
		$this->name = $name;
		$this->date = $date;
		$this->type = $type;
		$max = ceil(count($results) / ServerLogManager::getInstance()->getMaxCount());
		if($page > $max)
			$page = $max;
		$this->page = $page;
		$this->max = $max;
	}

	public function jsonSerialize() : array{
		$lang = ServerLogManager::getInstance()->getLang();
		$results = [];
		$count = 0;
		foreach($this->results as $result){
			$count++;
			if($count >= ($this->page * ServerLogManager::getInstance()->getMaxCount() - (ServerLogManager::getInstance()->getMaxCount() - 1)) and $count <= ($this->page * ServerLogManager::getInstance()->getMaxCount())){
				$results[] = $result;
			}
		}
		return [
			"type" => "form",
			"title" => str_replace(["{player}", "{date}", "{type}"], [$this->name, $this->date, $lang->getNested($this->type)], $lang->getNested("form.searchResult.byPlayer.title")),
			"content" => implode("\n", $this->results),
			"buttons" => [
				["text" => $lang->getNested("form.searchResult.byPlayer.exit")],
				["text" => $lang->getNested("form.searchResult.byPlayer.return")],
				["text" => str_replace(["{now}", "{max}"], [$this->page, $this->max], $lang->getNested("form.searchResult.byPlayer.nextPage"))]
			]
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if(is_int($data)){
			switch($data){
				case 1:
					$player->sendForm(new LogMainForm());
					break;
				case 2:
					if($this->page < $this->max){
						$this->page = $this->page + 1;
						$player->sendForm($this);
					}
					break;
			}
		}
	}
}