<?php

namespace org\Khinenw\xcel;

use org\Khinenw\xcel\event\PlayerGameChangeEvent;
use pocketmine\Player;
use pocketmine\Server;

class XcelPlayer{
	public $dataBundle;
	private $game;
	private $player;

	const PLAYER_STATUS_NO_GAME = -1;
	const PLAYER_STATUS_ALIVE = 0;
	const PLAYER_STATUS_DEAD = 1;

	const BUNDLE_KEY_CURRENT_STATUS = "currentStatus";

	private static $defaultDataBundle = [
		self::BUNDLE_KEY_CURRENT_STATUS => self::PLAYER_STATUS_NO_GAME
	];

	public function __construct(Player $player){
		$this->player = $player;
		$this->game = null;

		$this->dataBundle = self::$defaultDataBundle;
	}

	public function setBundleData($key, $value){
		$this->dataBundle[$key] = $value;
	}

	public function getBundleData($key){
		return $this->dataBundle[$key];
	}

	public function isAlive(){
		return $this->dataBundle["currentStatus"] === self::PLAYER_STATUS_ALIVE;
	}

	/**
	 * @return XcelGame
	 */
	public function getGame(){
		return $this->game;
	}

	public function setGame($game){
		$this->game = $game;
		$this->dataBundle = self::$defaultDataBundle;

		Server::getInstance()->getPluginManager()->callEvent(new PlayerGameChangeEvent($this));
	}

	public function getPlayer(){
		return $this->player;
	}
}
