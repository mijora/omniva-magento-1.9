<?php

$installer = $this;

$installer->startSetup();

$setup = $this;

$installer->addAttribute("order", "parcel_terminal", array("type"=>"varchar"));
$installer->addAttribute("quote", "parcel_terminal", array("type"=>"varchar"));

$installer->addAttribute("order", "manifest_generation_date", array("type"=>"varchar"));

$installer->endSetup();