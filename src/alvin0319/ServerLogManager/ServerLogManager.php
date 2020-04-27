<?php
declare(strict_types=1);
namespace alvin0319\ServerLogManager;

use alvin0319\ServerLogManager\form\LogMainForm;
use pocketmine\block\Block;
use pocketmine\block\SignPost;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;

class ServerLogManager extends PluginBase implements Listener{

	public static $prefix = "";

	/** @var Config */
	protected $config;

	/** @var Config */
	protected $commandLog;

	protected $commandLogDB = [];

	/** @var Config */
	protected $chatLog;

	protected $chatLogDB = [];

	protected $blockLogTemp = [];

	protected $queue = [];

	/** @var Config */
	protected $lang;

	/** @var ServerLogManager */
	private static $instance;

	public function onLoad() : void{
		self::$instance = $this;
	}

	public static function getInstance() : ServerLogManager{
		return self::$instance;
	}

	public function onEnable() : void{
		@mkdir($this->getDataFolder() . "command/");
		@mkdir($this->getDataFolder() . "chat/");
		@mkdir($this->getDataFolder() . "block/");

		$this->saveResource("config.yml");

		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

		date_default_timezone_set($this->getConfig()->getNested("timezone"));

		$this->commandLog = new Config($this->getDataFolder() . "command/" . date("Y-m-d") . ".yml", Config::YAML, []);
		$this->commandLogDB = $this->commandLog->getAll();

		$this->chatLog = new Config($this->getDataFolder() . "chat/" . date("Y-m-d") . ".yml", Config::YAML, []);
		$this->chatLogDB = $this->chatLog->getAll();

		$this->saveResource($this->getConfig()->getNested("lang", "eng") . ".yml");

		$this->lang = new Config($this->getDataFolder() . $this->getConfig()->getNested("lang", "eng") . ".yml", Config::YAML);

		self::$prefix = $this->lang->getNested("prefix", "§b§l[Log] §r§7");

		$command = new PluginCommand($this->lang->getNested("commands.log", "log"), $this);
		$command->setDescription($this->lang->getNested("commands.description", ""));
		$command->setPermission("logManager.command");
		$this->getServer()->getCommandMap()->register("log", $command);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable() : void{
		$this->commandLog->setAll($this->commandLogDB);
		$this->commandLog->save();
		$this->chatLog->setAll($this->chatLogDB);
		$this->chatLog->save();
		foreach($this->blockLogTemp as $xyz => $data){
			$config = new Config($this->getDataFolder() . "block/" . $xyz . ".yml", Config::YAML);
			$config->setAll($data);
			$config->save();
		}
	}

	public function getConfig() : Config{
		return $this->config;
	}

	public function blockLog(Player $player, Block $pos, string $type, string $extraData = "") : void{
		$posString = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ() . ":" . $pos->getLevel()->getFolderName();
		if(!isset($this->blockLogTemp[$posString])){
			$this->blockLogTemp[$posString] = [];
		}
		if($pos instanceof SignPost){
			$tile = $pos->getLevel()->getTile($pos);
			if($tile instanceof Sign){
				$extraData = implode(" ", array_map(function(int $index, string $line) : string{
					return "[{$index}] " . $line;
				}, array_keys($tile->getText()), $tile->getText()));
			}
		}
		$this->blockLogTemp[$posString][] = ["date" => date($this->lang->getNested("date-format", "Y-m-d H:i:s")), "issuer" => $player->getName(), "extraData" => $extraData, "type" => $type];
	}

	public function chatLog(Player $player, string $message) : void{
		//$str = "[" . date($this->lang->getNested("date-format", "Y-m-d H:i:s")) . "] " . $player->getName() . ": " . $message;
		$this->chatLogDB[] = [
			"issuer" => $player->getName(),
			"message" => $message,
			"date" => date($this->lang->getNested("date-format", "Y-m-d H:i:s"))
		];
	}

	public function commandLog(Player $player, string $message) : void{
		//$str = "[" . date($this->lang->getNested("date-format", "Y-m-d H:i:s")) . "] " . $player->getName() . ": " . $message;
		$this->commandLogDB[] = [
			"issuer" => $player->getName(),
			"command" => $message,
			"date" => date($this->lang->getNested("date-format", "Y-m-d H:i:s"))
		];
	}

	public function loadBlockConfig(Block $block) : ?Config{
		if(file_exists($file = $this->getDataFolder() . "block/" . ($xyz = $block->getFloorX() . ":" . $block->getFloorY() . ":" . $block->getFloorZ() . ":" . $block->getLevel()->getFolderName()))){
			if(!isset($this->blockLogTemp[$xyz])){
				$this->blockLogTemp[$xyz] = [];
			}
			$this->blockLogTemp[$xyz] = ($config = new Config($file))->getAll();
			return $config;
		}
		return null;
	}

	/**
	 * @param BlockPlaceEvent $event
	 * @priority HIGHEST
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if(!$event->isCancelled()){
			$this->blockLog($player, $block, "place");
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @priority HIGHEST
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if(!$event->isCancelled()){
			$this->blockLog($player, $block, "break");
		}
	}

	public function onSignChange(SignChangeEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if(!$event->isCancelled()){
			$this->blockLog($player, $block, "sign");
			if($this->getConfig()->getNested("settings.notice-op.sign", true)){
				foreach($this->getServer()->getOnlinePlayers() as $op){
					if($op->isOp()){
						$op->sendMessage("§b§l[" . $this->lang->getNested("sign-log", "CommandLog") . "] §r§7" . $player->getName() . ": " . implode(" ", array_map(function(int $index, string $line) : string{
							return "[{$index}] " . $line;
						}, array_keys($event->getLines()) , $event->getLines())));
					}
				}
			}
			if($this->getConfig()->getNested("settings.notice-console.command", true)){
				$this->getServer()->getLogger()->info("§b§l[" . $this->lang->getNested("sign-log", "CommandLog") . "] §r§7" . $player->getName() . ": " . implode(" ", array_map(function(int $index, string $line) : string{
					return "[{$index}] " . $line;
				}, array_keys($event->getLines()) , $event->getLines())));
			}
		}
	}

	/**
	 * @param PlayerCommandPreprocessEvent $event
	 * @priprity HIGHEST
	 */
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) : void{
		$player = $event->getPlayer();
		$command = $event->getMessage();
		if((substr($command, 0, 1) === "/") or (substr($command, 0, 2) === "./")){// Support ./command ...
			if(!$event->isCancelled()){
				$this->commandLog($player, $command);
				if($this->getConfig()->getNested("settings.notice-op.command", false)){
					foreach($this->getServer()->getOnlinePlayers() as $op){
						if($op->isOp()){
							$op->sendMessage("§b§l[" . $this->lang->getNested("command-log", "CommandLog") . "] §r§7" . $player->getName() . ": " . $command);
						}
					}
				}
				if($this->getConfig()->getNested("settings.notice-console.command", true)){
					$this->getServer()->getLogger()->info("§b§l[" . $this->lang->getNested("command-log", "CommandLog") . "] §r§7" . $player->getName() . ": " . $command);
				}
			}
		}
	}

	/**
	 * @param PlayerChatEvent $event
	 * @priority HIGHEST
	 */
	public function onPlayerChat(PlayerChatEvent $event) : void{
		$player = $event->getPlayer();
		$message = $event->getMessage();
		if(!$event->isCancelled()){
			$this->chatLog($player, $message);
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if(isset($this->queue[$player->getName()])){
			$xyz = $block->getFloorX() . ":" . $block->getFloorY() . ":" . $block->getFloorZ() . ":" . $block->getLevel()->getFolderName();
			$player->sendMessage(ServerLogManager::$prefix . str_replace("{pos}", $xyz, $this->lang->getNested("message.block-log")));
			if(isset($this->blockLogTemp[$xyz])){
				$str = "";
				foreach($this->blockLogTemp[$xyz] as $key => $data){
					$temp = str_replace(["{date}", "{issuer}", "{type}"], [$data["date"], $data["issuer"], $this->lang->getNested($data["type"])], $this->lang->getNested("message.block-log-format"));
					$str .= ($str === "[{$key}] " . $temp ? '' : "\n[{$key}] " . $temp);
				}
				$player->sendMessage("§7" . $str);
			}elseif($this->loadBlockConfig($block) instanceof Config){
				$str = "";
				foreach($this->blockLogTemp[$xyz] as $key => $data){
					$temp = str_replace(["{date}", "{issuer}", "{type}"], [$data["date"], $data["issuer"], $this->lang->getNested($data["type"])], $this->lang->getNested("message.block-log-format"));
					$str .= ($str === "[{$key}] " . $temp ? '' : "\n[{$key}] " . $temp);
				}
				$player->sendMessage("§7" . $str);
			}
		}
	}

	private function recursiveUnlink(string $dir) : void{
		if(substr($dir, -1) !== "/"){
			$dir .= "/";
		}
		foreach(scandir($dir) as $file){
			if(!in_array($file, [".", ".."])){
				$realPath = $dir . $file;
				if(is_dir($realPath)){
					$this->recursiveUnlink($realPath);
				}else{
					unlink($realPath);
				}
			}
		}
		rmdir($dir);
	}

	public function getLang() : Config{
		return $this->lang;
	}

	public function getMaxCount() : int{
		return intval($this->getConfig()->getNested("max-log-count-per-page", 50));
	}

	public function setBlockLogMode(Player $player) : bool{
		if(isset($this->queue[$player->getName()])){
			unset($this->queue[$player->getName()]);
			return false;
		}else{
			$this->queue[$player->getName()] = true;
			return true;
		}
	}

	public function getChatLogs() : array{
		return $this->chatLogDB;
	}

	public function getCommandLogs() : array{
		return $this->commandLogDB;
	}

	public function getChatLogsFor(string $date) : array{
		if(file_exists($file = $this->getDataFolder() . "chat/" . $date . ".yml")){
			return (new Config($file, Config::YAML))->getAll();
		}
		return [];
	}

	public function getCommandLogsFor(string $date) : array{
		if(file_exists($file = $this->getDataFolder() . "command/" . $date . ".yml")){
			return (new Config($file, Config::YAML))->getAll();
		}
		return [];
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			$sender->sendForm(new LogMainForm());
		}
		return true;
	}
}