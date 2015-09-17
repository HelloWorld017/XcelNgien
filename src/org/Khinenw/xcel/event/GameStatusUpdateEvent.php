<?php

namespace org\Khinenw\xcel\event;

use org\Khinenw\xcel\XcelGame;
use org\Khinenw\xcel\XcelNgien;
use pocketmine\event\plugin\PluginEvent;

class GameStatusUpdateEvent extends PluginEvent{
	private $game;
	public static $handlerList;

	public function __construct(XcelGame $game){
		parent::__construct(XcelNgien::getInstance());
		$this->game = $game;
	}

	public function getGame(){
		return $this->game;
	}
}
