<?php

namespace org\Khinenw\xcel\task;

use org\Khinenw\xcel\XcelNgien;
use pocketmine\scheduler\PluginTask;

class TickTask extends PluginTask{
	public function __construct(XcelNgien $plugin){
		parent::__construct($plugin);
	}

	public function onRun($currentTick){
		XcelNgien::tick();
	}
}