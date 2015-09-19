<?php

/*
	\-\     /-/  .          .              . |--|
	 \ \   / /   .   /----| .    / /--\ \  . |  |
	  \ \ / /    .  /  ---| .   / /----\ \ . |  |
	   \   /     . /  /     .   | |------| . |  |
	   /   \     . \  \     .   | |        . |  |
	  / / \ \    .  \  ---| .   \ \-----|  . |  |
	 / /   \ \   .   \----| .    \------|  . |  |
	/-/     \-\  .          .              . |--|
	          Welcome to Xceled Ngine
                   by Khinenw
        "Convenient, Stable, yet Extendable"
            "This project applies GPLv3"
 */

namespace org\Khinenw\xcel;

use gamecore\gcframework\GCFramework;
use org\Khinenw\xcel\task\TickTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class XcelNgien extends PluginBase implements Listener{
	private static $instance;
	/**
	 * @var $worlds XcelGame[]
	 */
	public static $worlds = [];

	/**
	 * @var $games \ReflectionClass[]
	 */
	public static $games = [];

	/**
	 * @var $players XcelPlayer[]
	 */
	public static $players = [];

	public static $translations = [];
	public static $configs = [];

	public function onEnable(){
		@mkdir($this->getDataFolder());
		self::$instance = $this;

		if(!is_file($this->getDataFolder() . "worlds.json")){
			file_put_contents($this->getDataFolder() . "worlds.json", json_encode([]));
		}

		$this->pushFile("translation_ko.yml");
		$this->pushFile("translation_en.yml");
		$this->pushFile("config.yml");

		self::$configs = (new Config($this->getDataFolder() . "config.yml", Config::YAML))->getAll();

		$lang = "en";
		if(isset(self::$configs["lang"])){
			if(is_file($this->getDataFolder() . "translation" . self::$configs["lang"] . "yml")){
				$lang = self::$configs["lang"];
			}
		}

		self::$translations = (new Config($this->getDataFolder() . "translation_$lang.yml", Config::YAML))->getAll();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this), 1);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @return XcelNgien
	 */
	public static function getInstance(){
		return self::$instance;
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "sgw":
				if(count($args) != 2) return false;

				if(!isset(self::$games[$args[0]])){
					$sender->sendMessage(TextFormat::RED . self::getTranslation("UNKNOWN_GAME"));
					return true;
				}

				if($this->getServer()->getLevelByName($args[1]) === null){
					$sender->sendMessage(TextFormat::RED . self::getTranslation("UNKNOWN_LEVEL"));
					return true;
				}

				if(isset(self::$worlds[$args[1]])){
					$sender->sendMessage(TextFormat::RED . self::getTranslation("WORLD_ALREADY_OCCUPIED"));
					return true;
				}

				$worlds = json_decode($this->getDataFolder()."worlds.json", true);

				if(!isset($worlds[$args[0]])){
					$worlds[$args[0]] = [];
				}

				$worlds[$args[0]][$args[1]] = [
					"config" => self::$games[$args[0]]->getStaticPropertyValue("defaultConfigs")
				];

				file_put_contents($this->getDataFolder()."worlds.json", json_encode($worlds));

				$sender->sendMessage(TextFormat::AQUA . self::getTranslation("PLEASE_RESTART_SERVER"));
		}

		return true;
	}

	public function pushFile($fileName){
		if(!is_file($this->getDataFolder().$fileName)){
			$res = $this->getResource($fileName);
			file_put_contents($this->getDataFolder().$fileName, stream_get_contents($res));
			fclose($res);
		}
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if(!isset(self::$players[$event->getPlayer()->getName()])){
			self::$players[$event->getPlayer()->getName()] = new XcelPlayer($event->getPlayer());
		}else{
			$event->getPlayer()->kick(self::getTranslation("ALREADY_LOGGED_IN"), false);
		}
	}

	public function onPlayerTeleport(EntityTeleportEvent $event){
		$player = $event->getEntity();

		if(!$player instanceof Player) return;

		if(!isset(self::$players[$player->getName()])) return;

		$xcelPlayer = self::$players[$player->getName()];

		if(($fromLevel = $event->getFrom()->getLevel()->getFolderName()) !== ($toLevel = $event->getTo()->getLevel()->getFolderName())){
			if(isset(self::$worlds[$toLevel])){
				if(self::$worlds[$toLevel] === $xcelPlayer->getGame()){
					return;
				}

				self::$worlds[$toLevel]->warpPlayerTo($xcelPlayer);
			}

			if($xcelPlayer->getGame() !== null){
				$xcelPlayer->getGame()->onPlayerMoveToAnotherWorld($xcelPlayer);
			}

			if(isset(self::$worlds[$toLevel])){
				self::$worlds[$toLevel]->warpPlayerTo($xcelPlayer);
			}
		}
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event){
		if(isset(self::$players[$event->getPlayer()->getName()])){
			$xcelPlayer = self::$players[$event->getPlayer()->getName()];
			$event->setRespawnPosition($this->getServer()->getDefaultLevel()->getSpawnLocation());
			if($xcelPlayer->getGame() !== null){
				$xcelPlayer->getGame()->onPlayerMoveToAnotherWorld($xcelPlayer);
			}
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if(!isset(self::$players[$event->getPlayer()->getName()])){
			return;
		}

		$xcelPlayer = self::$players[$event->getPlayer()->getName()];
		if($xcelPlayer->getGame() !== null) $xcelPlayer->getGame()->onPlayerQuit($xcelPlayer);
		unset(self::$players[$event->getPlayer()->getName()]);
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();

		if(!isset(self::$players[$player->getName()])) return;

		$xcelPlayer = self::$players[$player->getName()];

		if(($fromLevel = $event->getFrom()->getLevel()->getFolderName()) !== ($toLevel = $event->getTo()->getLevel()->getFolderName())){
			if($xcelPlayer->getGame() !== null){
				$xcelPlayer->getGame()->onPlayerMoveToAnotherWorld($xcelPlayer);
			}

			if(isset(self::$worlds[$toLevel])){
				self::$worlds[$toLevel]->warpPlayerTo($xcelPlayer);
			}
		}
	}

	public function onEntityDamage(EntityDamageEvent $event){
		if($event->isCancelled()) return;

		$player = $event->getEntity();
		if(!$player instanceof Player) return;
		if(!isset(self::$players[$player->getName()])) return;

		$xcelPlayer = self::$players[$player->getName()];
		if(($game = $xcelPlayer->getGame()) === null) return;

		switch($game->getStatus()){
			case XcelGame::STATUS_PREPARING:
				$event->setCancelled();
				break;

			case XcelGame::STATUS_IN_GAME:
				if($event instanceof EntityDamageByEntityEvent){
					$damager = $event->getDamager();
					if($damager instanceof Player){
						if(isset(self::$players[$damager->getName()])){
							$xcelDamager = self::$players[$damager->getName()];
							if($this->isSameGame($xcelPlayer->getGame(), $xcelDamager->getGame()) && $xcelDamager->getGame()->canPvP($xcelDamager, $xcelPlayer)){

							}else{
								$event->setCancelled();
								break;
							}
						}else{
							$event->setCancelled();
							break;
						}
					}
				}else{
					if(!$xcelPlayer->getGame()->canBeDamaged($xcelPlayer, $event)){
						$event->setCancelled();
						switch($event->getCause()){
							case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
							case EntityDamageEvent::CAUSE_CONTACT:
							case EntityDamageEvent::CAUSE_CUSTOM:
							case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
							case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION:
							case EntityDamageEvent::CAUSE_FALL:
							case EntityDamageEvent::CAUSE_FIRE:
							case EntityDamageEvent::CAUSE_FIRE_TICK:
							case EntityDamageEvent::CAUSE_MAGIC:
							case EntityDamageEvent::CAUSE_PROJECTILE:
								break;

							case EntityDamageEvent::CAUSE_DROWNING:
							case EntityDamageEvent::CAUSE_LAVA:
							case EntityDamageEvent::CAUSE_SUFFOCATION:
							case EntityDamageEvent::CAUSE_SUICIDE:
							case EntityDamageEvent::CAUSE_VOID:
								$event->getEntity()->teleport($game->getWorld()->getSpawnLocation());
								break;
						}
						break;
					}
				}

				if($player->getHealth() <= $event->getFinalDamage()){
					$event->setCancelled();
					$xcelPlayer->getGame()->onPlayerDeath($xcelPlayer);
				}
		}
	}

	public static function registerGame(\ReflectionClass $game){
		$uniqueGameName = $game->getMethod("getUniqueGameName")->invoke(null);
		self::$games[$uniqueGameName] = $game;
		$i = 0;
		$json = json_decode(file_get_contents(self::getInstance()->getDataFolder()."worlds.json"), true);

		if(isset($json[$uniqueGameName])){
			foreach($json[$uniqueGameName] as $worldName => $worldData){
				$i++;

				self::$worlds[$worldName] = $game->newInstance(
					self::getInstance()->getServer()->getLevelByName($worldName),
					$worldData["config"],
					$i
				);
			};
		}

		self::getInstance()->getLogger()->info(TextFormat::AQUA . "Game " . $uniqueGameName . " have been registered!");
	}

	public static function onGameWin(XcelGame $game, array $winner){
		$winnerDisplayName = [];
		$winnerName = [];

		/**
		 * @var $xcelPlayer XcelPlayer
		 */
		foreach($winner as $xcelPlayer){
			$winnerDisplayName[] = $xcelPlayer->getPlayer()->getDisplayName();
			$winnerName[] = $xcelPlayer->getPlayer()->getName();
		}

		if(count($winner) > 0){
			$text = TextFormat::AQUA.self::getTranslation("FINISHED_WINNER", $game->getServerId(), $game->getGameName(), implode(", ", $winnerDisplayName));
		}else{
			$text = TextFormat::AQUA.self::getTranslation("FINISHED_NO_WINNER", $game->getServerId(), $game->getGameName());
		}

		GCFramework::getFramework()->onGameFinish($game->getUniqueGameName(), $winnerName, $text);
	}

	public static function isSameGame(XcelGame $game, XcelGame $game2){
		return (($game->getServerId() === $game2->getServerId()) && ($game->getUniqueGameName() === $game2->getUniqueGameName()));
	}

	public static function tick(){
		foreach(self::$worlds as $game){
			$game->onTick();
		}
	}

	public static function getTranslation($key, ...$args){
		if(!isset(self::$translations[$key])){
			return $key.", ".implode(", ", $args);
		}

		$translation = self::$translations[$key];

		foreach($args as $key => $value){
			$translation = str_replace("%s".($key + 1), $value, $translation);
		}

		return $translation;
	}

	public static function getXcelPlayerByPlayer(Player $player){
		if(!isset(self::$players[$player->getName()])) return null;
		return self::$players[$player->getName()];
	}

	public static function getGameByWorldName($worldName){
		if(!isset(self::$worlds[$worldName])) return null;
		return self::$worlds[$worldName];
	}
}
