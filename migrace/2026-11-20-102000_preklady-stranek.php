<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English content (stranky.obsah_en) of the CMS pages an English visitor reaches first: the intro pages of the activity
// lines, the menu, the tea room, helping to organise, sponsors and media, the programme legend and turbo tournaments
// (the photo galleries of the lines in 2026-11-20-124420_anglicke-fotogalerie-linii.php). The Czech content (obsah)
// stays untouched. More pages are translated in 2026-11-20-102100_preklady-stranek-2.php.
// Links, image paths, e-mail addresses and placeholders (%…%) are the same as in the Czech content; relative links
// stay in the page language thanks to <base href>.

$preklady = [];

$preklady['o-turnajich-v-deskovkach'] = <<<'MD'
# Board game tournaments

Love competing and want to test yourself against strong opponents? Got a favourite game and want to see how others play it? Come and play in a tournament and win one of the beautiful board games on offer!
MD;

$preklady['o-larpech-na-gc'] = <<<'MD'
# Larps

In a (chamber) larp you dive into another world for a few hours. There is no fixed script like an actor has in a film – you and your fellow players create the story. There is no audience either, you play only for each other.

You negotiate, scheme and try to reach your goals or to live your character to the full. It doesn't matter whether you have any acting experience, you play the way that comes naturally to you. The point is the experience, so you can't really get a larp wrong.
MD;

$preklady['o-prednaskach-na-gc'] = <<<'MD'
# Talks and workshops

Look forward to **free talks** and discussions with people who live for games – from seasoned players to designers and guests from the community!

**The talk times shown are approximate.** You will find the exact times in the description of each talk, or at GameCon itself on the programme posters.

Besides talks, you can also choose from **hands-on workshops** with more room for questions, interaction and trying things yourself – these are **paid**.
MD;

$preklady['o-rpg-na-gc'] = <<<'MD'
# RPG

RPGs are role-playing games in which you and a group of fellow players tell a shared and unique story. You step into the shoes of a character in the story, describe what they do and speak for them.

Dice and rules come into the story too, bringing uncertainty and suspense. You don't need any props or a special setting, your own imagination is all it takes – much like when you read a book.
MD;

$preklady['o-wargamingu-na-gc'] = <<<'MD'
# Wargaming

Ever fancied leading an army, but climbing the whole career ladder from private to general never seemed worth it? In wargaming you can become that general right now!

Wargaming means battles with gorgeous miniatures on a detailed board (terrain) that lets you play out any battle you can imagine. Every game has slightly different rules, but you will almost always reach for a tape measure and dice.
MD;

$preklady['o-bonusech-na-gc'] = <<<'MD'
# Action games and bonuses

Don't fancy spending all of GameCon sitting indoors? This section can get you nicely soaked, properly tired out and it will give your brain a good workout too! Light and easy summer games are as much a part of the holidays as they are a part of GameCon.
MD;

$preklady['legendy-klubu-dobrodruhu'] = <<<'MD'
# Legends of the Adventurers’ Club

Experience a linked RPG set in the world of [Tales of the Empire](http://imperium.mytago.cz/) (see below), where the stories cross over between groups of players! The game is split into two parts – the first group starts a story that the second group then continues, and the other way round.

**You don't need to know the system or the setting**, we will explain everything at the start of the first round. It is not so much about competing – even though some results will be announced – as about the experience of linked, open play itself.

## Game runs

Each run has two games, each led by a different game master. You choose the first game.

- Run 1 (Thursday afternoon + Friday morning): played on Thursday 14:00–18:00 and on Friday 09:00–13:00
- Run 2 (Thursday evening + Friday afternoon): played on Thursday 19:00–23:00 and on Friday 14:00–18:00
- Run 3 (Friday evening + Saturday afternoon): played on Friday 19:00–23:00 and on Saturday 14:00–18:00

---

<blockquote>
Please give others the chance to play Legends too and don't play more than one run. Thank you!
</blockquote>
MD;

$preklady['drd'] = <<<'MD'
# DrD Championship Archive

The DrD Championship was a long-running tournament in Dračí doupě (DrD), the original Czech fantasy role-playing game. In the thirty years it was part of GameCon, hundreds of parties took part in it and took home countless unforgettable gaming experiences.

This page is an archive of past years, the adventures played and related materials.

If you would like to play in a tournament, GameCon now runs two others. Find out more about the Legends of the Adventurers’ Club [here](https://gamecon.cz/legendy) and about the D&D/JaD tournament (Dungeons & Dragons and its Czech edition Jeskyně a draci) [here](dnd).

[Older adventures](drd/archiv)

[Hall of fame](drd/sin-slavy)
MD;

$preklady['o-epickych-deskovkach'] = <<<'MD'
# Epic board games

Drawn to big board games with beautiful components, but put off by complicated rules? You don't need to study anything, the game master will explain everything and make sure the game runs smoothly!

Epic board games tend to be highly interactive, they have elements of conflict and diplomacy, and luck plays a part too. The focus is on atmosphere rather than on game dynamics; the games often draw on fantasy, sci-fi or history, which makes for a deeper gaming experience.
MD;

$preklady['o-doprovodnem-programu'] = <<<'MD'
# Side programme

GameCon starts in grand style, ends in even grander style, and in between it is all about having fun!
Want a break from thrilling RPGs and strategic board games? Come and unwind at a concert, enjoy a community meet-up or join the Celohra, the festival-wide game in the spirit of this year's theme!
MD;

$preklady['o-deskoherne'] = <<<'MD'
# Board game library

The board game library has hundreds of modern board and card games just waiting for you to try them, any time during GameCon! Don't know the rules? Our Hermans in orange T-shirts saying “Herman” will be happy to explain them. Nobody to play with? Try asking the people around you, they may be looking for someone to play with too.

The board game library is open **every day from 8:00 to 3:00 (on Thursday from 14:00, on Sunday until 14:00)** and **it is free**.

For keener players we have a section of [epic board games](http://gamecon.cz/epic) and we run [tournaments](http://gamecon.cz/turnaje), so don't forget to check them out too.
MD;

$preklady['dnd-info'] = <<<'MD'
# D&D/JaD tournament

A tournament for all players of **Dungeons & Dragons** and of its compatible Czech edition **Jeskyně a draci**. Test your detective and role-playing skills, have fun and create a story with your party that will lead you together to victory and to lifting the travelling trophy.

On this page you will find everything you need to know about the D&D/JaD tournament – how the tournament is organised and its rules, this year's adventure and the game masters who will run it.
MD;

$preklady['celohra'] = <<<'MD'
# Celohra

## What is Celohra?

Celohra is the one game that never gets old! Every year it is written especially for the current GameCon theme, it has a new story and new game mechanics, and it is a great way to fill the free time in your programme, because you can jump in whenever you like. Play while there is time!

## General rules of Celohra<sup>[1](celohra#fn:footnote)</sup>

* Celohra starts on Thursday after the opening ceremony and ends on Sunday at 10:00.
* You can start the game at any <span style="color:blue">blue</span> station.
* No player may come back to the same station **within half an hour**.

The game is based on fair play. It leaves some game situations entirely in the players' hands, and they will be as good as you (in your group) make them for each other. Celohra is a scored activity and you can win small prizes in it. Scoring is mainly about your positive contribution to your group, being visible in your team and in Celohra as such, and how much you get involved in the game overall.

## FAQ

### How do I start playing?

- The “Thursday evening” way: On Thursday evening there will be a group activity for current and would-be players. The best option if you don't manage to join earlier.
- The “I want to figure it out myself” way: Find one of the <span style="color:blue">blue</span> stations. Most of them should nudge you in the right direction.
- The “Quick and efficient” way: Find one of the players who are already playing. They usually know how the game works and can get newcomers into the game _quickly and efficiently_.

Confused (and not so confused) questions and comments about Celohra can be sent to <celohra@gamecon.cz>.

---
<ol>
<li id="fn:footnote">may change slightly before GameCon starts.</li>
</ol>
MD;

$preklady['jidelnicek'] = <<<'MD'
# Menu

You can order breakfasts, lunches and dinners provided by Menza VŠB (the canteen) in your **[registration](https://gamecon.cz/prihlaska)**, or **at the info desk by the morning of the day before at the latest**.

Meals are served **in the Menza**, except for breakfast for hotel guests.

## Breakfast

Breakfast is a buffet:

- for participants in standard rooms – in the Menza from 8:00 to 10:00;
- for hotel guests – in the hotel restaurant on the ground floor of building B from 6:30 to 9:30.

## Lunch and dinner

Lunch is served to everyone in the Menza from 12:00 to 14:00 (on Sunday 13:00–15:00) and dinner from 17:30 to 19:30. You don't order a specific dish in advance, so not every dish may be available for the whole serving time.

## Thursday

**Lunch**

Soup: Cream of pumpkin soup with curry

1. Chicken gyros, roast potatoes, yoghurt dip
2. Chicken roulade with spinach, boiled Parisienne potatoes
3. Ratatouille, roast potatoes, herb soy mayonnaise

**Dinner**

1. Spaghetti bolognese topped with Parmesan
2. Chicken roulade with spinach, boiled Parisienne potatoes
3. Bean goulash with soy meat, jasmine rice

## Friday

**Lunch**

Soup: Tomato soup with egg drops

1. Pork with tomatoes (gluten-free), jasmine rice
2. Sous-vide turkey steak, boiled Parisienne potatoes
3. Fried cauliflower, boiled Parisienne potatoes, homemade tartare sauce

**Dinner**

1. Roast pork with hoisin sauce and Chinese noodles
2. Pork with tomatoes (gluten-free), jasmine rice
3. Dukátové buchtičky (small sweet buns) with vanilla custard

## Saturday

**Lunch**

Soup: Cauliflower soup with potatoes

1. Meatballs in cream sauce, potato wedges
2. Chicken curry with coconut milk, vegetables and jasmine rice
3. Roast tofu bites, sautéed beetroot with marjoram, crushed potatoes

**Dinner**

1. Fried chicken strips, boiled Parisienne potatoes, dip
2. Honey-glazed pork chop, boiled Parisienne potatoes, dip
3. Cauliflower and chickpea curry with coconut milk and rice

## Sunday

**Lunch**

Soup: Kulajda (creamy dill soup with mushrooms and potatoes)

1. Pork goulash, bread dumplings
2. Roast chicken leg, jasmine rice
3. Chinese noodles with curried vegetables topped with fried onion
MD;

$preklady['gamecon/cajovna'] = <<<'MD'
# Tea room

What is it, actually, this tea room? For some, the answer that it is a place where tea is drunk will do. That answer is true, but it is very dry and far from complete.

A tea room can take, and both in times past and today has taken, many forms, and none of them can be said to be the one true tea room. It does not matter whether it is just a kettle and a few bowls, a single mug and an immersion heater, a dedicated pavilion in a Japanese garden or a building the size of a hangar with a staff of a hundred.

Much more important than the place and the equipment is the ability to create a space where visitors feel good and safe, where they can relax, chat, simply think, settle disputes calmly or close deals. A place where visitors can have fun, be entertained and entertain others. A place you will gladly come back to, even if it is at another time, in another place and different.

You don't have to come to the tea room to drink tea. You don't need the knowledge of a tea master. You don't need to know anything about tea to be let in. You don't even need to know what tea actually is, and what the difference is between a tea, a brew and a potion. You don't even have to like tea, and you will still be welcome.

Tea has a history many centuries long, and over those centuries people have invented many ways to grow it, process it, prepare it, serve it, drink it, write verses about it, paint pictures of it ... how to behave in a tea room, over tea, towards tea.

You can put all of that out of your head and come to the tea room just to sit for a while and let your thoughts drift. You can chat with the beings who came with you, and with beings who turned up in the tea room without any help from you.

Together with them, or on your own, you can play, write, draw, create or simply think, daydream and forget the world, or let yourself be carried away by the atmosphere of the place.

You may meet known and unknown scribes, rhymesters, bards and bardesses, sketchers, painters, players and dungeon masters, humans, elves, sprites and little critters, storytellers and listeners, seers and smart alecks, beings both spellcasting and spellbinding.

You can celebrate holidays here, birthdays and unbirthdays, victories or successes. You can also take part in the odd christening or happening, if any happen to happen.

All of that you can do. And on top of that, you can also enjoy a cup of tea or two.

<p style="font-weight: bold; text-align: right;">Melkor</p>
MD;

$preklady['organizacni-vypomoc'] = <<<'MD'
# I want to help organise GameCon

This page is for those interested in organising. If you want to help **as a volunteer** on site, **sign up in the [programme](https://gamecon.cz/program/streda)** for one of the volunteer activities (Příprava – setting up, Úklid – cleaning up). And if you fancy **running an activity**, get in touch with the head of that programme section. You will find their contact **on the relevant Activities subpage**.

We organise GameCon in our free time as volunteers and enthusiasts who want to keep improving the festival and to pass the joy on to other participants. Want to lend a hand? Thank you! Rather than professionals, we are looking for independent, active people who will enjoy taking part in running a medium-sized organisation.

## What's in it for you?

Organising GameCon is a free-time activity, but it has its perks too. Depending on how involved you are, you get various benefits and you can even have the whole of GameCon for free.

Besides that, you will get to know lots of new people with similar interests, whom you will meet several times a year at joint and section meet-ups.

Becoming part of the team is a chance to look behind the scenes of a festival for more than a thousand people, to learn professional organising tools and to learn from more experienced people – whether you are into graphic design, IT, games, writing, facilities, grants or anything else. We will be glad to let you in on all the secrets of organising, teach you the software and methods we work with, and you will gain great experience that will be useful not only in everyday life, but will also enrich your CV.

## Who are we looking for right now?

-   **First aider** – you need medical training or a first aid course and the willingness to keep two blocks free during GC.
-   **Printing and posters** – all it takes is being happy to deal with print shops and having time on the Wednesday and Thursday before GameCon.

Don't hesitate to write to us even if the position you would enjoy isn't on the list. We certainly won't close the door on capable people, and there is almost always something to do.

Not sure what you would enjoy? Tell us what you can do or what interests you, and together we will surely figure out how to get you involved.

## How to contact us

If you want to become part of the team, write to us at [info@gamecon.cz](mailto:info@gamecon.cz) and we will get back to you.
MD;

$preklady['pro-sponzory-a-media'] = <<<'MD'
# Who we are

GameCon is the largest multi-genre festival of non-computer games in Central Europe, bringing together the communities of board games, tabletop RPGs, larps, wargaming and other games every summer. Besides the games themselves, it also offers action games and physical activities, talks, workshops, tournaments and the Celohra, a game that ties together the programme of the whole festival.

It is open to experienced players and long-time participants as well as to complete beginners. The chance to try different kinds of games and play styles and to share experiences with other players is part of what makes GameCon special.

![](https://gamecon.cz/soubory/obsah/presskit/GameCon_logo.png)

A set of photos free to use is available [here](https://gamecon.cz/soubory/obsah/presskit/GameCon_foto_set.zip).


## Media contact

If you would like to write about us or film a report at the festival, contact us at info@gamecon.cz. We will help you choose the most interesting time to take photos and video and arrange visits to interesting activities that are open only by prior arrangement. If you are interested, we will be happy to show you around in person after the event or give you an interview.

If you would like to write an article about us, we have prepared a pack with information about the festival, a set of logos and photos from the event. You can download it [here](https://gamecon.cz/soubory/obsah/presskit/GameCon_press_kit.zip).

And if you would like to receive practical information about the event meant especially for the media (at most 3 e-mails a year), subscribe to our [newsletter](https://gamecon.cz/newslettery).



## Sponsor contact

If you are interested in supporting the event financially, in kind or in any other way, our colleagues who look after sponsorship and finance are at your disposal. You can reach them at finance@gamecon.cz.



## Written (and talked) about us

The articles and videos are in Czech.

GamingProfesors: [GameCon 2025: Co se dalo zažít na 30. ročníku legendární akce?](https://www.gamingprofessors.cz/gamecon-2025-co-se-dalo-zazit-na-30-rocniku-legendarni-akce/)

iDnes: [Pardubice zvou na GameCon, největší festival nepočítačových her](https://www.idnes.cz/pardubice/zpravy/pardubice-zvou-na-gamecon.A160712_112233_pardubice-zpravy_jah)

Česká Televize: [GameCon: festival nepočítačových her](https://www.ceskatelevize.cz/porady/1096902795-studio-6/217411010100720/cast/557913/)

UndergroundRPG: [Gamecon 2025 Rozhovorový speciál - Bude příští rok vůbec Gamecon?](https://www.youtube.com/watch?v=cFIchMuJ4KI)

ZeStolu: [Zúčastnili jsme se jubilejního 30. ročníku pardubického GameConu](https://www.zestolu.cz/deskovky/zucastnili-jsme-se-jubilejniho-30-rocniku-pardubickeho-gameconu-601824)

RPG Stvořitelna: [RPG Stvořitelna 51 - GameCon 2025](https://www.youtube.com/watch?v=ST8jzFrC51c)

FantasyMag: [GameCon: rozhovor s organizátorem největšího festivalu nepočítačových her v České republice plného deskovek, larpu, RPG a wargamingu](https://www.fantasymag.cz/gamecon-rozhovor-se-zakladatelem-nejvetsiho-festivalu-nepocitacovych-her-v-ceske-republice-plneho-deskovek-larpu-rpg-a-wargamingu/)



## GameCon on social media

GameCon on [Instagram](https://www.instagram.com/gamecon_cz/) or under the hashtag [#gameconcz](https://www.instagram.com/explore/tags/gameconcz/)

GameCon on [Discord](https://discord.gg/wT6c6vcXez)

GameCon on [YouTube](https://www.youtube.com/@GameconCz)
MD;

// the date is written in English on the English page (Stranka::html(), DatumVJazyce::podleFormatuZeSablony())
$preklady['program-legenda-text'] = <<<'MD'
Sign-ups for the next wave of activities: <b>%PRISTI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%</b>
MD;

$preklady['turnaje/turboturnaje'] = <<<'MD'
# Turbo tournaments

**YOU can win a turbo tournament too!**

Turbo tournaments are quick demo tournaments in small, simple and fun games that you don't even need to know beforehand – and you can still win! They are usually simple board games, card games or party games that can be played up to three times an hour – even with learning the game, a turbo tournament takes about an hour and a half.

Turbo tournaments are free and you don't even need to sign up in advance (but you can if you want – they are the **[tournaments](https://gamecon.cz/turnaje)** with #T in the activity name).

So next time you have a free block, drop by the board game library – you will learn something simple, maybe meet new people, get a game in and perhaps even take home a valuable prize!

So don't hesitate and stop by to try one or two.
MD;

foreach ($preklady as $urlStranky => $obsahEn) {
    $obsahEnSql = $this->connection->real_escape_string($obsahEn);
    $urlStrankySql = $this->connection->real_escape_string($urlStranky);
    $this->q(<<<SQL
UPDATE stranky
SET obsah_en = '{$obsahEnSql}'
WHERE url_stranky = '{$urlStrankySql}'
SQL,
    );
}

// The photo galleries of the lines (`*/fotogalerie`) are not here: they are Instagram embeds, their English content
// is made from the Czech one in 2026-11-20-124420_anglicke-fotogalerie-linii.php.
