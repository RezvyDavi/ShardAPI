<?php

declare(strict_types=1);

namespace Rezvy\ShardAPI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\SingletonTrait;

class ShardAPI extends PluginBase implements Listener
{
    use SingletonTrait;

    private Config $shardData;
    private Config $config;

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        $this->shardData = new Config($this->getDataFolder() . "shards.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    protected function onDisable(): void
    {
        $this->shardData->save();
    }

    public function onPlayerLogin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        if (!$this->shardData->exists($name)) {
            $defaultShard = $this->config->get("default-shard", 0);
            $this->shardData->set($name, $defaultShard);
            $this->shardData->save();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "shard":
                return $this->handleShardCommand($sender, $args);
            case "giveshard":
                return $this->handleGiveShardCommand($sender, $args);
            case "takeshard":
                return $this->handleTakeShardCommand($sender, $args);
            case "setshard":
                return $this->handleSetShardCommand($sender, $args);
            case "topshard":
                return $this->handleTopShardCommand($sender);
            default:
                return false;
        }
    }

    private function handleShardCommand(CommandSender $sender, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->config->get("player-only", "§cThis command can only be used by players!"));
            return true;
        }

        $shard = $this->getShards($sender);
        $message = str_replace(["{player}", "{shard}"], [$sender->getName(), $shard], $this->config->get("shard-check", "§aYour shard balance: §e{shard}"));
        $sender->sendMessage($message);
        return true;
    }

    private function handleGiveShardCommand(CommandSender $sender, array $args): bool
    {
        if (!$sender->hasPermission("shardapi.admin")) {
            $sender->sendMessage($this->config->get("no-permission", "§cYou don't have permission to use this command!"));
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage($this->config->get("give-usage", "§cUsage: /giveshard <player> <amount>"));
            return true;
        }

        $targetName = $args[0];
        $amount = (int)$args[1];

        if ($amount <= 0) {
            $sender->sendMessage($this->config->get("invalid-amount", "§cAmount must be greater than 0!"));
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix($targetName);
        if ($target === null) {
            $sender->sendMessage(str_replace("{player}", $targetName, $this->config->get("player-not-found", "§cPlayer {player} not found!")));
            return true;
        }

        $this->addShards($target, $amount);

        $senderMessage = str_replace(["{player}", "{amount}"], [$target->getName(), $amount], $this->config->get("give-success", "§aYou gave §e{amount} §ashards to §e{player}"));
        $sender->sendMessage($senderMessage);

        $targetMessage = str_replace(["{amount}", "{sender}"], [$amount, $sender->getName()], $this->config->get("give-received", "§aYou received §e{amount} §ashards from §e{sender}"));
        $target->sendMessage($targetMessage);

        return true;
    }

    private function handleTakeShardCommand(CommandSender $sender, array $args): bool
    {
        if (!$sender->hasPermission("shardapi.admin")) {
            $sender->sendMessage($this->config->get("no-permission", "§cYou don't have permission to use this command!"));
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage($this->config->get("take-usage", "§cUsage: /takeshard <player> <amount>"));
            return true;
        }

        $targetName = $args[0];
        $amount = (int)$args[1];

        if ($amount <= 0) {
            $sender->sendMessage($this->config->get("invalid-amount", "§cAmount must be greater than 0!"));
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix($targetName);
        if ($target === null) {
            $sender->sendMessage(str_replace("{player}", $targetName, $this->config->get("player-not-found", "§cPlayer {player} not found!")));
            return true;
        }

        $currentShards = $this->getShards($target);
        if ($currentShards < $amount) {
            $sender->sendMessage(str_replace(["{player}", "{amount}"], [$target->getName(), $currentShards], $this->config->get("insufficient-shards", "§c{player} only has {amount} shards!")));
            return true;
        }

        $this->reduceShards($target, $amount);

        $senderMessage = str_replace(["{player}", "{amount}"], [$target->getName(), $amount], $this->config->get("take-success", "§aYou took §e{amount} §ashards from §e{player}"));
        $sender->sendMessage($senderMessage);

        $targetMessage = str_replace(["{amount}", "{sender}"], [$amount, $sender->getName()], $this->config->get("take-removed", "§c{amount} §ashards were taken from you by §e{sender}"));
        $target->sendMessage($targetMessage);

        return true;
    }

    private function handleSetShardCommand(CommandSender $sender, array $args): bool
    {
        if (!$sender->hasPermission("shardapi.admin")) {
            $sender->sendMessage($this->config->get("no-permission", "§cYou don't have permission to use this command!"));
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage($this->config->get("set-usage", "§cUsage: /setshard <player> <amount>"));
            return true;
        }

        $targetName = $args[0];
        $amount = (int)$args[1];

        if ($amount < 0) {
            $sender->sendMessage($this->config->get("invalid-amount", "§cAmount must be 0 or greater!"));
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix($targetName);
        if ($target === null) {
            $sender->sendMessage(str_replace("{player}", $targetName, $this->config->get("player-not-found", "§cPlayer {player} not found!")));
            return true;
        }

        $this->setShards($target, $amount);

        $senderMessage = str_replace(["{player}", "{amount}"], [$target->getName(), $amount], $this->config->get("set-success", "§aYou set §e{player}'s §ashard balance to §e{amount}"));
        $sender->sendMessage($senderMessage);

        $targetMessage = str_replace("{amount}", (string)$amount, $this->config->get("set-updated", "§aYour shard balance has been set to §e{amount}"));
        $target->sendMessage($targetMessage);

        return true;
    }

    private function handleTopShardCommand(CommandSender $sender): bool
    {
        $allShards = $this->shardData->getAll();

        if (empty($allShards)) {
            $sender->sendMessage($this->config->get("top-empty", "§cNo data available!"));
            return true;
        }

        arsort($allShards);

        $topPlayers = array_slice($allShards, 0, 10, true);

        $header = $this->config->get("top-header", "§e§l--- Top 10 Shard Leaders ---");
        $sender->sendMessage($header);

        $position = 1;
        $format = $this->config->get("top-format", "§e#{position} §f{player} §7- §a{shard} shards");

        foreach ($topPlayers as $playerName => $shardAmount) {
            $message = str_replace(
                ["{position}", "{player}", "{shard}"],
                [$position, $playerName, $shardAmount],
                $format
            );
            $sender->sendMessage($message);
            $position++;
        }

        $footer = $this->config->get("top-footer", "§e§l---------------------------");
        $sender->sendMessage($footer);

        return true;
    }

    public function getShards(Player $player): int
    {
        $name = strtolower($player->getName());
        return $this->shardData->get($name, 0);
    }

    public function setShards(Player $player, int $amount): void
    {
        $name = strtolower($player->getName());
        $this->shardData->set($name, $amount);
        $this->shardData->save();
    }

    public function addShards(Player $player, int $amount): void
    {
        $current = $this->getShards($player);
        $this->setShards($player, $current + $amount);
    }

    public function reduceShards(Player $player, int $amount): bool
    {
        $current = $this->getShards($player);
        if ($current < $amount) {
            return false;
        }
        $this->setShards($player, $current - $amount);
        return true;
    }

    public function hasShards(Player $player, int $amount): bool
    {
        return $this->getShards($player) >= $amount;
    }

    public function getAllShards(): array
    {
        return $this->shardData->getAll();
    }

    public function getTopPlayers(int $limit = 10): array
    {
        $allShards = $this->shardData->getAll();
        arsort($allShards);
        return array_slice($allShards, 0, $limit, true);
    }
}
