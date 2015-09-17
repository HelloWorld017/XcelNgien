<?php

namespace org\Khinenw\xcel;

use org\Khinenw\xcel\event\GameStatusUpdateEvent;
use org\Khinenw\xcel\event\PlayerRecalculationEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

abstract class XcelGame implements IGame{
	private $world;

	/**
	 * @var $players XcelPlayer[]
	 */
	public $players;
	protected $innerTick;
	protected $roundTick;
	protected $currentStatus;
	protected $aliveCount;

	const STATUS_NOT_STARTED = 0;
	const STATUS_PREPARING = 1;
	const STATUS_IN_GAME = 2;
	const STATUS_FINISHED = 3;

	const MESSAGE_SEND_TERM = 15;

	protected $configs = [];

	public static $defaultConfigs = [
		"need-players" => 2,
		"game-term" => 4200,
		"preparation-term" => 300
	];

	//starts from 1
	private $serverId;

	public function __construct(Level $world, $config, $serverId){
		$this->world = $world;
		$this->serverId = $serverId;
		$this->configs = $config;

		$this->resetGame();
	}

	public function resetGame(){
		Server::getInstance()->getPluginManager()->callEvent(new GameStatusUpdateEvent($this));
		$this->currentStatus = self::STATUS_NOT_STARTED;
		$this->players = [];
		$this->aliveCount = 0;
		$this->innerTick = 0;
		$this->roundTick = 0;
	}

	public function prepareGame(){
		Server::getInstance()->getPluginManager()->callEvent(new GameStatusUpdateEvent($this));
		$this->currentStatus = self::STATUS_PREPARING;
		$this->roundTick = 0;
	}

	public function startGame(){
		if($this->currentStatus !== self::STATUS_PREPARING) return;

		$this->roundTick = 0;

		if(count($this->players) < $this->configs["need-players"]){
			$this->broadcastMessageForPlayers(TextFormat::RED . XcelNgien::getTranslation("PREPARATION_DELAYED"));
			return;
		}

		$this->currentStatus = self::STATUS_IN_GAME;

		$this->broadcastMessageForPlayers(TextFormat::AQUA . XcelNgien::getTranslation("GAME_STARTED"));

		foreach($this->players as $xcelPlayer){
			$this->givePrivilege($xcelPlayer);
			$this->explainGame($xcelPlayer);
		}

		Server::getInstance()->getPluginManager()->callEvent(new GameStatusUpdateEvent($this));
	}

	//$winner is array of XcelPlayer
	public function winGame(array $winner){
		XcelNgien::onGameWin($this, $winner);
		$this->finishGame();
	}

	protected function finishGame(){
		$this->currentStatus = self::STATUS_FINISHED;
		foreach($this->players as $player){
			$this->removePlayer($player);
		}

		$this->resetGame();
	}

	public function onTick(){
		$this->innerTick++;
		$this->roundTick++;

		if(($this->innerTick % self::MESSAGE_SEND_TERM) === 0){
			foreach($this->players as $xcelPlayer){
				$xcelPlayer->getPlayer()->sendTip($this->getTip($xcelPlayer));
			}
		}

		switch($this->currentStatus){
			case self::STATUS_NOT_STARTED:
				break;

			case self::STATUS_PREPARING:
				if($this->roundTick >= $this->configs["preparation-term"]){
					$this->startGame();
				}

				foreach($this->players as $xcelPlayer){
					$xcelPlayer->getPlayer()->teleport($this->getPreparationPosition($xcelPlayer));
				}

				break;

			case self::STATUS_IN_GAME:
				if($this->roundTick >= $this->configs["game-term"]){
					$alive = [];
					foreach($this->players as $xcelPlayer){
						if($xcelPlayer->isAlive()) $alive[] = $xcelPlayer;
					}

					$this->winGame($alive);
				}
		}

		$this->afterTick();
	}

	public function afterTick(){

	}

	public function broadcastMessageForPlayers($message){
		foreach($this->players as $xcelPlayer){
			$xcelPlayer->getPlayer()->sendMessage($message);
		}
	}

	public function onPlayerDeath(XcelPlayer $player){
		$this->failPlayer($player);
	}

	public function onPlayerQuit(XcelPlayer $player){
		$this->failPlayer($player, false);
		$this->removePlayer($player, true);
	}

	public function onPlayerMoveToAnotherWorld(XcelPlayer $player){
		$this->removePlayer($player);
	}

	public function failPlayer(XcelPlayer $player, $notify = true){
		if(!$player->isAlive()) return;

		if($this->currentStatus === self::STATUS_IN_GAME){
			$player->setBundleData(XcelPlayer::BUNDLE_KEY_CURRENT_STATUS, XcelPlayer::PLAYER_STATUS_DEAD);
			$player->getPlayer()->setGamemode(3);
			$player->getPlayer()->teleport($this->world->getSpawnLocation());
			if($notify) $this->broadcastMessageForPlayers(TextFormat::RED . XcelNgien::getTranslation("PLAYER_FAILED", $player->getPlayer()->getDisplayName()));
		}else{
			self::removePlayer($player);
		}

		$this->recalculatePlayers();
	}

	public function removePlayer(XcelPlayer $player, $notify = false){
		if($notify && $player->isAlive()){
			$this->broadcastMessageForPlayers(TextFormat::RED . XcelNgien::getTranslation("PLAYER_OUT", $player->getPlayer()->getDisplayName()));
		}

		if($this->currentStatus === self::STATUS_IN_GAME || $this->currentStatus === self::STATUS_FINISHED){
			$this->removePrivilege($player);
		}
		$player->setGame(null);
		$player->getPlayer()->setGamemode(Server::getInstance()->getDefaultGamemode());
		$player->getPlayer()->setHealth($player->getPlayer()->getMaxHealth());
		$player->getPlayer()->teleport(Server::getInstance()->getDefaultLevel()->getSpawnLocation());

		unset($this->players[$player->getPlayer()->getName()]);
		$this->recalculatePlayers();
	}

	public abstract function removePrivilege(XcelPlayer $player);
	public abstract function givePrivilege(XcelPlayer $player);
	public abstract function getTip(XcelPlayer $player);
	public abstract function explainGame(XcelPlayer $player);
	public abstract function canPvP(XcelPlayer $attacker, XcelPlayer $victim);
	public abstract function canBeDamaged(XcelPlayer $player, EntityDamageEvent $event);
	public abstract function getPreparationPosition(XcelPlayer $player);

	public function recalculatePlayers(){
		$alive = array();
		$playerCount = 0;

		foreach($this->players as $xcelPlayer){
			$playerCount++;
			if($xcelPlayer->isAlive()) $alive[] = $xcelPlayer;
		}

		$this->aliveCount = count($alive);

		switch($this->currentStatus){
			case self::STATUS_IN_GAME:
				if($this->aliveCount <= 1){
					$this->winGame($alive);
				}
				break;

			case self::STATUS_NOT_STARTED:
				if($playerCount >= $this->configs["need-players"]){
					$this->prepareGame();
				}
				break;
		}

		Server::getInstance()->getPluginManager()->callEvent(new PlayerRecalculationEvent($this));
	}

	public function canWarpTo(XcelPlayer $player){
		if(($player->getGame() !== null) && ($player->getBundleData(XcelPlayer::BUNDLE_KEY_CURRENT_STATUS) !== XcelPlayer::PLAYER_STATUS_NO_GAME)) return false;
		return ($this->currentStatus !== self::STATUS_IN_GAME);
	}

	public function warpPlayerTo(XcelPlayer $player){
		if(!$this->canWarpTo($player)) return;

		$this->players[$player->getPlayer()->getName()] = $player;

		$player->setGame($this);
		$player->setBundleData(XcelPlayer::BUNDLE_KEY_CURRENT_STATUS, XcelPlayer::PLAYER_STATUS_ALIVE);

		$this->recalculatePlayers();
	}

	public function getServerId(){
		return $this->serverId;
	}

	public function getWorld(){
		return $this->world;
	}

	public function getStatus(){
		return $this->currentStatus;
	}

	public function getAliveCount(){
		return $this->aliveCount;
	}

	public function getConfiguration(){
		return $this->configs;
	}

	//public abstract function getWarpText();
}
