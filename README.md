# ShardAPI – Developer API Documentation

This document explains how to use **ShardAPI** inside other PocketMine-MP plugins.
The API provides functions to retrieve, add, set, and manipulate shard balances for players.

## Getting the API Instance

The API uses `SingletonTrait`, so you can access it from anywhere:

```php
use Rezvy\ShardAPI\ShardAPI;

/** @var ShardAPI $api */
$api = ShardAPI::getInstance();
```

## Retrieving a Player's Shard Balance

You can retrieve a player's shard balance using the `getShards` method:

```php
/** @var Player $player */
$amount = $api->getShards($player);
```

## Setting a Player's Shard Balance

Use `setShards` to directly set a player's shard amount:

```php
/** @var Player $player */
$api->setShards($player, 100);
```

## Adding Shards to a Player

Use `addShards` to increase the player's shard balance:

```php
/** @var Player $player */
$api->addShards($player, 25);
```

## Reducing a Player's Shard Balance

Use `reduceShards` to remove shards from a player.  
The method returns `true` if deduction succeeds, or `false` if the player lacks enough shards.

```php
/** @var Player $player */
$success = $api->reduceShards($player, 10);

if ($success) {
    // deduction successful
}
```

## Checking if a Player Has Enough Shards

Use `hasShards` to check whether a player meets a required amount:

```php
/** @var Player $player */
if ($api->hasShards($player, 50)) {
    // player can afford something
}
```

## Retrieving All Stored Shard Data

`getAllShards` returns an associative array of all saved shard values:

```php
$data = $api->getAllShards();
```

### Example Output

```json
{
    "rezvy": 120,
    "alex": 75,
    "steve": 30
}
```

## Getting the Top Shard Holders

Use `getTopPlayers` to retrieve players with the highest shard balance:

```php
$top = $api->getTopPlayers(5);
```

### Example Output

```json
{
    "rezvy": 120,
    "alex": 75,
    "steve": 30,
    "david": 20,
    "lucas": 10
}
```

## Example Usage in a Custom Plugin

```php
use Rezvy\ShardAPI\ShardAPI;
use pocketmine\player\Player;

function buyItem(Player $player, int $cost): void {
    $api = ShardAPI::getInstance();

    if (!$api->hasShards($player, $cost)) {
        $player->sendMessage("§cYou don't have enough shards!");
        return;
    }

    $api->reduceShards($player, $cost);
    $player->sendMessage("§aPurchase successful!");
}
```
