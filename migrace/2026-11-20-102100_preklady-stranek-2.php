<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English content of further CMS pages for the English website (/en/…), see 2026-11-20-101509_stranky-obsah-en.php.
// Only obsah_en is set, the Czech obsah stays as it is. The HTML structure, styles, image paths, anchors and relative
// links are the same as in the Czech content; relative links stay relative, the website keeps them in the page language.
$obsahEn = [];

$obsahEn['prakticke-informace'] = <<<'MD'
# Practical information
This year GameCon takes place <b>%GC_BEZI_OD|datum:FORMAT_DATUM_LETOS%–%GC_BEZI_DO|datum:FORMAT_DATUM_LETOS%</b>

Registration opens
%REG_GC_OD|datum:FORMAT_ZACATEK_UDALOSTI%

First programme wave
%PRVNI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%

Second programme wave
%DRUHA_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%

Third programme wave
%TRETI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%

First wave of cancelling unpaid orders (the money must be in the GameCon account by then)
%HROMADNE_ODHLASOVANI_1|datum:FORMAT_ZACATEK_UDALOSTI%

Second wave of cancelling unpaid orders (the money must be in the GameCon account by then)
%HROMADNE_ODHLASOVANI_2|datum:FORMAT_ZACATEK_UDALOSTI%

Merch orders close %PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE|datum:FORMAT_DATUM_LETOS%

Accommodation orders close %UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE|datum:FORMAT_DATUM_LETOS% (after that, only on site)

Food orders close %JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE|datum:FORMAT_DATUM_LETOS% (after that, only on site)

## Quick links: brochure, site maps and menu

To make your experience even better, we’ve prepared a **[guide
brochure](https://gamecon.cz/soubory/obsah/download/brozura_2026.pdf)** (in Czech) with all the important information, maps, links and contacts. We recommend downloading it to your phone so you have it at hand when you actually need it!

You can download the **site map** separately [here](https://gamecon.cz/soubory/obsah/materialy/obecne/ostrava_mapa_arealu.pdf). A more detailed **map of the indoor spaces** is [here](https://gamecon.cz/soubory/obsah/materialy/obecne/ostrava_mapa_vnitrnich_prostor.pdf).

And the **menu is on [this page](https://gamecon.cz/jidelnicek)**.


## Registering for GameCon and prices
<p>There is <b>no entry fee</b> at GameCon, so you can take part in the festival for its whole duration completely <b>free of charge</b>. You only pay for the activities, accommodation and food you <b>actually order</b>. The board game library, talks and part of the side programme are free!</p>

 <p>All you need to do is create a GameCon account and, once registration opens, fill in the <a href="prihlaska">registration form</a>.</p>

<p>You <b>pay for your orders in <a href="finance">Finances</a></b> by bank transfer. Don’t forget to <b>settle your balance before the 1st and 2nd waves of cancellations</b> (dates above or in <a href="finance">Finances</a>). If your orders stay unpaid, we’ll have to cancel some of them.</p>

<p>And if you <b>order extra activities during GameCon</b>, don’t forget to <b>check your balance at the end and either pay online or stop by the info desk</b> (you can pay by instant bank transfer or in cash).</p>


## How activities work

We warmly recommend trying sections you don’t know too! Unless stated otherwise, all games are suitable for newcomers and the game masters will be happy to show you the ropes. You’ll find everything you need to know in the descriptions of the individual lines of activities.

<details>
<summary>Programme waves and signing up for activities</summary>
<article>
<p>Instead of everything opening at once, signing up for activities is split into several waves, which helps spread the rush. <b>If you miss the first wave, don’t despair – the second and third offer plenty of interesting options that are just as much fun</b>.</p>

<p>Once a programme wave opens, you can sign up for an activity on the page of its line or through the <a href="program">Programme</a>. Many activities have limited capacity, so <b>we recommend signing up as soon as they open</b> to make sure you get a place.</p>
 	<p>The exceptions are:
        <ul>
<li><p><b>Board game library</b>
You don’t need to sign up for the board game library at all – just register for GameCon, check in at the info desk and you can play for free.</p></li>
<li><p><b>Talks</b>
Talks have no capacity limit and are free for participants; just sign up for them either through the <a href="program">Programme</a> or on their <a href="prednasky">line of activities</a>.</p></li>
</ul></p>

</article>
</details>

 <details>
    <summary>Watching activities</summary>
    <article>
        <p>If an activity that caught your eye is already full, you can start watching it. You’ll then <b>get an e-mail notification</b> when someone drops out, and you can take the place. Watch out: the number of watchers isn’t limited, so someone may still beat you to it – <b>we recommend reacting quickly</b>.</p>

<p>So that activities aren’t blocked for other participants, <b>you can’t be signed up for one activity and watch another one at the same time</b>. You have to decide whether you’d rather hope that a place frees up, or go for a programme that appeals to you less but is a sure thing.</p>

<p>Feel free to use watching during GameCon too – people drop out of many games literally at the last minute, so you might manage to snag a place.</p>
</details>

<details id="tymovky">
    <summary>Team activities</summary>
    <article>
<p>You’ll recognise team activities by the <i>týmová</i> (team) tag. You’ll come across them most often in the D&D tournament, the Legends of the Adventurers’ Club and in action games and bonuses.</p>

<p>Signing up for team activities has two parts – the captain and the team members.</p>

<p>The <b>captain</b> is whoever signs up for the team activity first and marks themselves as captain. Start by signing up for the activity as usual, then click <i>Založit nový tým</i> (Create a new team) in the window that pops up. If the activity allows it, also choose the rounds you want to play in, and then confirm the team with the <i>Přihlásit se jako kapitán</i> (Sign up as captain) button.</p>

<p style="text-align:center;">
    <img src="https://gamecon.cz/soubory/obsah/aktivity/tymovky/Tymovky_1.png"
         alt="How to sign up as team captain"
         style="max-width: 450px; width: 100%; height: auto; border: 1px solid #d0d0d0; border-radius: 6px; margin: 10px 0;">
</p>

<p>From that moment, you have <b>72 hours during which the team can be edited</b> before it gets locked. You can change its name, or have one generated by clicking the dice icon. You’ll also see a four-character code – send it to the other team members so they can join. You can change the team size and decide whether the team is private, meaning people can join only with the code, or public, so anyone can join. Once the team is complete and you’re happy with it, just click <i>Zamknout tým a začít hrát</i> (Lock the team and start playing), which closes the team so it can no longer be edited.</p>

<p style="text-align:center;">
    <img src="https://gamecon.cz/soubory/obsah/aktivity/tymovky/Tymovky_2.png"
         alt="Editing a team"
         style="max-width: 450px; width: 100%; height: auto; border: 1px solid #d0d0d0; border-radius: 6px; margin: 10px 0;">
</p>

<p><b>Only the captain can edit the team like this</b>. If you close the team editing by mistake, you can get back to it by clicking <i>tým</i> (team) next to the activity in your programme.</p>

<p style="text-align:center;">
    <img src="https://gamecon.cz/soubory/obsah/aktivity/tymovky/Tymovky_3.png"
         alt="How to get back to editing a team"
          style="max-width: 450px; width: 100%; height: auto; border: 1px solid #d0d0d0; border-radius: 6px; margin: 10px 0;">
</p>

<p>For the <b>other players in the team</b>, signing up is even easier. Sign up for the activity as usual and enter the code the captain sends you into the team code field, or sign up for a public team and click <i>Připojit se</i> (Join). Now just wait for the captain to lock the team and you’re all set.</p>

<p style="text-align:center;">
    <img src="https://gamecon.cz/soubory/obsah/aktivity/tymovky/Tymovky_1.png"
         alt="How to sign up as a team member"
          style="max-width: 450px; width: 100%; height: auto; border: 1px solid #d0d0d0; border-radius: 6px; margin: 10px 0;">
</p>

</details>

<details>
    <summary>Getting to your activities</summary>
   <article>
        <p><b>Meet 15 minutes before the activity starts</b> at the meeting point (Sraziště), by the flag of the activity’s section. You’ll find the meeting point on the open space <b>in front of the halls of residence</b>. If you aren’t there on time, the game master may call you (keep your phone switched on), or your place may go to a substitute!</p>

<p>If you know in advance that <b>you’ll be late</b> but still plan to come, <b>let the info desk know, in person or by phone</b>. We’ll pass the message on to the game master.</p>

<p><b>For a talk, go straight to the talks room. To get there, go through the lobby into building B, turn right just behind the door, and at the end of the corridor on the right you’ll find a door with a sign saying Přednášky (Talks)</b>.</p>

<p><b>You can drop in to the board game library</b> every day from 8:00 until 3:00 at night (from 14:00 on Thursday and until 14:00 on Sunday). You’ll find it in the gyms and in the corridor between buildings A and C, behind the info desk.</p>
</article>
</details>

<details>
    <summary>What if I can’t come – cancellation terms</summary>
   <article>
<p> <b>If you can’t come</b>, <b>ideally cancel as soon as you know</b> (through the <a href="program">programme</a> or at the info desk). It makes it easier for us to find substitutes, and you’ll also avoid paying the full cancellation fee.</p>

<p> The cancellation terms work like this:
<ul>
<li><p>If you cancel <b>more than 24 hours in advance</b>, you won’t be charged any cancellation fee.</p></li>
<li><p> If you cancel <b>less than 24 hours in advance</b>, you’ll pay half the price of the activity.</p></li>
<li><p>If you <b>don’t turn up at all</b>, you’ll have to pay the full amount.</p></li>
</ul></p>
</details>

<details>
    <summary>Substitutes</summary>
   <article>
<p>People drop out of many activities literally at the last minute, so you may be lucky and grab a place in a game even without signing up for it in advance. <b>Hang around the substitutes’ flag</b>, or step forward if you see a game master looking for a substitute.</p>

<p>As a substitute, <b>you pay the full price of the activity</b>; the game master adds the activity to your programme afterwards by entering you in the attendance list. So <b>at the end of GameCon, make sure your balance isn’t in the red</b> – if it is, either settle it online or stop by the info desk (instant bank transfer or cash).</p>
</details>


## Minors and children

If you are <b>under 15</b>, send us a scan of the signed [parental consent form](https://gamecon.cz/soubory/obsah/materialy/2026/souhlas_rodicu_2026.pdf) (in Czech) for taking part in GameCon at [info@gamecon.cz](mailto:info@gamecon.cz) in advance.

When choosing activities, check their <b>age limit</b> (every activity has a tag with the minimum age). If you are below the age limit but think you can handle the game, try getting in touch with the head of the section (you’ll find their contact on the section’s page under Activities) and agree on it directly with them.

If you only want to supervise a child during an activity, <b>arrange it in advance</b> with the game master through the head of the section, to find out whether it’s possible. If not, please respect the game master’s wishes.


## Rules of participation

<details>
    <summary>1. Don’t be an idiot</summary>
    <article>
        <p>If something interesting crosses your mind, try to think it through before you do it – especially in the evening! Be considerate of yourself and others. If you behave inappropriately or dangerously, a game master may exclude you from an activity and/or an organiser from GameCon, even without giving a reason or a refund. And for good. It has never happened so far and we’d like to keep it that way.</p>
    </article>
</details>

<details>
    <summary>2. Drink only as much as you can handle</summary>
    <article>
        <p>If you throw up somewhere, we’re the ones who have to deal with it (and clean it up), which certainly doesn’t add to our fun or mood – and surely not to that of anyone else around either.</p>
</details>

<details>
    <summary>3. Quiet hours</summary>
    <article>
        <p>They exist and GameCon is no exception. Some participants want to sleep, and many organisers and game masters really need to, so that GameCon works for you too. And we want to stay on good terms with the university and the police.</p>

        <p>So keep the mess to a minimum – both noise and litter – outside in the street and in the corridors. For late nights, head to the Night Lounge (Noční kecárna) in the Vrtule club, or to the board game library.</p>
</details>

<details>
    <summary>4. Try to help us</summary>
    <article>
        <p>We can’t be everywhere. If you come across a problem or something missing, try to fix it yourself, or let us know at the info desk or through an organiser.</p>
</details>


<details>
    <summary>5. Meet us halfway</summary>
    <article>
        <p>When we ask for something or tell you something, we have a reason for it, but not always the time (or energy) to explain it. Please do it, even if it seems strange or pointless to you.</p>
    </article>
</details>


##The venue

<details>
    <summary>Where does GameCon take place and how do I get there?</summary>
    <article>
        <p><b>GameCon</b> takes place in <b>Ostrava, at the halls of residence of VSB – Technical University of Ostrava</b>. The address is <b>Studentská 1770/1, Ostrava-Poruba</b> (in Waze, just enter “GameCon”).</p>

        <p><b>From Ostrava-Svinov railway station</b> you can get there <b>by bus</b> (stop Poruba, Studentské koleje) or <b>by tram</b> from Svinovské mosty (stop VŠB-TUO), followed by a few minutes’ walk through the campus. You can buy a ticket right on board with a contactless card.</p>

        <p>If you come by car, you can park in the surrounding streets, but note that finding a free space may be harder during GameCon. <b>Street parking around the halls is free, and there are also large car parks on the campus, but some of them are paid</b>.</p>
</article>
</details>

<details>
    <summary>Is GameCon suitable for wheelchair users?</summary>
    <article>
        <p><b>The venue is wheelchair accessible</b>. There are also several accessible units for accommodation. If you need a bed in one of them, write to us at <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.</p>

<p>However, some activities may not be wheelchair accessible – typically some larps or action games. If you want to check a specific programme, write to the head of the section. You’ll find their e-mail on the section’s page.</p>
</article>
</details>

<details>
    <summary>Can I bring a pet?</summary>
    <article>
        <p>Sorry, no pets are allowed at GameCon.</p>
</article>
</details>

<details>
 <summary>Info desk</summary>
    <article>

        <p><b>When you arrive, go to the info desk first</b>; that’s where you sort out your registration, accommodation, materials, money and everything else. Don’t hesitate to stop by the info desk later too, whenever you need to sort something out during GameCon.</p>
        <p>
            <b>Opening hours</b>:
        <ul>
            <li>Wednesday from about 16:00</li>
            <li>Thursday 9:00–21:00</li>
            <li>Friday 8:30–21:00</li>
            <li>Saturday 8:30–20:00</li>
            <li>Sunday 8:30–16:00</li>
        </ul>
        </p>
<p>Before you leave, <b>settle any amount you still owe</b> for activities, accommodation or food added later, online or at the info desk. If you’ve paid too much, you can either keep the credit for next year or we can refund it – preferably by bank transfer.</p>
    </article>
</details>

<details>
    <summary>Night Lounge and quiet hours</summary>
    <article>
        <p>You’ll find the Night Lounge (Noční kecárna) <b>in the Vrtule club</b>. It will be open every day until 3:00.</p>

        <p>When socialising outside, please remember that <b>night-time quiet hours apply by law</b>. And when you’re inside, be considerate of those who are already asleep. Any trouble would fall on GameCon too, and we all want to stay on good terms with the halls and the police.</p>
    </article>
</details>


<h2 id="ubytovani">Accommodation at GameCon</h2>

   <p><b>You can order accommodation <a href="prihlaska">in the registration form</a></b>. You always order just a bed, not a whole room, and every participant who wants to stay overnight has to book it individually. In the registration form you can also say who you’d like to share a room with, and if possible, we’ll arrange it.</p>

<p>Check-in is possible <b>from Wednesday, from about 16:00</b>.</p>

<p><b>You can also order accommodation on site</b>. In that case, ask at the info desk about free beds. We’ll try to find you a place, but please note that we can’t guarantee the roommates you’d like, nor that the type of room you’d prefer will be available.</p>

<p><b>Before you leave</b>, tidy your room, strip the bed linen, take out the rubbish if it doesn’t fit in the bin, and return your key to the porter’s lodge. Please <b>leave your room by 14:00 on Sunday</b> (or by 9:00 on Monday if you volunteer to help clean up after GameCon).</p>

<p>If you order accommodation but then don’t come to GameCon, we can’t refund the money.</p>


<details>
    <summary>Classic rooms</summary>
    <article>
        <p>The accommodation is <b>in the halls of residence</b>. The rooms are grouped in units: a toilet and showers are shared by two rooms, the rooms have pillows, duvets and bed linen, and there is a fridge in the unit’s hallway. <b>Everyone staying will have their own room key</b> (losing a key costs a penalty of CZK 2,500, because it would mean replacing several locks and keys in the system).</p>

<p>A unit has either two double rooms, or one triple and one double room, so there are at most five of you sharing a unit. We can’t guarantee which type of unit you’ll be in.</p>

<p>A single room means a double room just for you – the other bed stays empty and nobody else will be put in with you.</p>
      </article>
</details>

<details>
    <summary>Hotel rooms</summary>
    <article>
<p>If you’re looking for more comfort and privacy, you can choose one of the hotel rooms. Compared with the classic accommodation, they offer <b>a quieter setting and nicer, more spacious rooms</b>, and <b>breakfast is included in the price</b> – a buffet in the hotel restaurant right in the building. <b>Everyone staying has their own room key</b> (losing a key costs a penalty of CZK 2,500, because it would mean replacing several locks and keys in the system).</p>

<p>The hotel rooms come in two categories – Standard and Deluxe.</p>

<p><b>Standard</b><br>
A unit has one single and one double room with a shared bathroom and toilet. The rooms have basic hotel amenities – a TV, a kettle, a fridge, a hairdryer and towels.</p>

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/jednoluzkovy-pokoj-standard.jpg"
     alt="Standard single room"
     width="250"
     style="margin-right: 10px;">

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/dvouluzkovy-pokoj-standard.jpg"
     alt="Standard double room"
     width="250"
     style="margin-right: 10px;">

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/koupelna-standard.jpg"
     alt="Standard bathroom"
     width="250">

<p><b>Deluxe</b><br>
<p>The more private option, which comes in three forms:
<br>– two single rooms with a shared bathroom and toilet,
<br>– a single room with its own bathroom and toilet,
<br>– a double room with its own bathroom and toilet.</p>

<p>The rooms are more spacious and better furnished than the Standard ones. The amenities are the same: a TV, a kettle, a fridge, a hairdryer and towels.</p>

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/jednoluzkovy-pokoj-deluxe.jpg"
     alt="Deluxe single room"
     width="250"
     style="margin-right: 10px;">

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/dvouluzkovy-pokoj-deluxe.jpg"
     alt="Deluxe double room"
     width="250"
     style="margin-right: 10px;">

<img src="https://gamecon.cz/soubory/obsah/obrazky/hotelove-pokoje-ostrava/koupelna-deluxe.jpg"
     alt="Deluxe bathroom"
     width="250">
</article>
</details>

<details>
    <summary>Accessible rooms</summary>
    <article>
   <p>There are several wheelchair-accessible units at the venue. If you’d like to stay in one, order a bed in the room type you want (single/double) and write to us at <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.</p>

<p>Otherwise, it’s the classic unit-type accommodation <b>in the halls of residence</b>. A unit consists of one single and one double room with a shared bathroom and toilet. The rooms have pillows, duvets and bed linen, and there is a fridge in the unit’s hallway. <b>Everyone staying will have their own room key</b> (losing a key costs a penalty of CZK 2,500, because it would mean replacing several locks and keys in the system).</p>
      </article>
</details>


## Food at GameCon

<details>
    <summary>Meals in the canteen</summary>
    <article>
        <p>In the <a href="prihlaska">registration form</a> you can also order <b>breakfasts</b>, <b>lunches</b> and <b>dinners</b> in advance, served by the VŠB university canteen (Menza VŠB). The menu will be available a few days before GameCon.</p>

        <p>Meals are served <b>in the canteen</b> at these times:
        <ul>
            <li>breakfast 8:00–10:00</li>
            <li>lunch 12:00–14:00 (on Sunday 13:00–15:00)</li>
            <li>dinner 17:30–19:30</li>
        </ul>
        </p>

<p>Meals are handed out for meal vouchers, which you get at the info desk when you arrive. You can also <b>order or cancel meals for the next day at the info desk, by the morning of the day before at the latest</b>. If you order a meal at the info desk, you get a voucher; if you cancel one, you hand the voucher back.</p>
    </article>
</details>

<details>
    <summary>Food truck and coffee stand next to the meeting point</summary>
    <article>

        <p><b>Next to the meeting point</b> you’ll find a <b>coffee stand</b> run by Laura Coffee, a local café and roastery, and a <b>food truck</b> all day long.</p>

<p>Food truck opening hours: 10:00–20:00</p>

<p>Coffee stand opening hours:
        <ul>
            <li>Thursday 14:00–19:15</li>
            <li>Friday 9:00–19:15</li>
            <li>Saturday 9:00–19:15</li>
            <li>Sunday 9:00–13:00</li>
        </ul>
        </p>


    </article>
</details>

<details>
    <summary>Vrtule club and bistro</summary>
    <article>

        <p>Opposite the halls of residence is the <b>Vrtule club and bistro</b>, where you can order food, drinks and alcohol. At night, <b>you’ll find the Night Lounge here</b>.</p>

<p>You can order hot food here, but bear in mind that it’s a regular restaurant, so it will take a while.</p>

<p>The bistro will serve food 14:00–22:00. You can order drinks, alcoholic or not, between 14:00 and 1:00.</p>

        </article>
</details>

<details>
    <summary>Tea room</summary>
    <article>

   <p><b>Behind the board game library</b> there’s a stand of the Smokehill tea room, where you can order <b>tea, snacks and shisha</b> – and borrow a smaller board game to go with them, for example.</p>

<p><p>The tea room will be open daily 8:30–2:00.</p>
    </article>
</details>


<details>
    <summary>Kitchenettes in the accommodation</summary>
    <article>

   <p>You can cook in the accommodation. Every floor has shared kitchenettes with a <b>ceramic hob and a microwave</b>. Note that they <b>have no dishes or cookware</b>. If you’ve booked accommodation, you’ll have a fridge in your unit.</p>
    </article>
</details>

<details>
    <summary>Drinks at the venue</summary>
    <article>

  <p>As for drinks, <b>there are water dispensers all around the venue</b> that you can use as much as you like.</p>

If you fancy a coffee, stop by the <b>coffee stand next to the meeting point</b>; for tea, pop into the <b>Smokehill tea room behind the board game library</b>; and for <b>alcohol</b>, head to <b>the Vrtule club</b>.
    </article>
</details>


## Important contacts at GameCon

<b>Info desk</b>: Got a problem (practical or any other)? The info desk will help you, or point you in the right direction. If it’s an emergency, or they’ve already closed, call <a href="tel:+420735513899">+420 735 513 899</a>.

  <p>
            <b>Opening hours</b>:
        <ul>
            <li>Wednesday from about 16:00</li>
            <li>Thursday 9:00–21:00</li>
            <li>Friday 8:30–21:00</li>
            <li>Saturday 8:30–20:00</li>
            <li>Sunday 8:30–16:00</li>
        </ul>
        </p>


<b>Medic</b>: Injured or ill? We have a medic at GameCon. You can reach them directly on <a href="tel:+420735540479">+420 735 540 479</a> or through the info desk.

<b>Kindred Spirit</b>: Has something unpleasant happened, or are you not feeling well and want to talk about it? Then let our Kindred Spirit (Spřízněná duše) know. During GameCon you can call <a href="tel:+420705943002">+420 705 943 002</a>; outside GameCon, write to [spriznenaduseGC@gmail.com](mailto:spriznenaduseGC@gmail.com). On the other end you’ll find our
qualified psychologist Míša, who will be very glad to help you. Really. And everything you tell her stays confidential, unless you want otherwise.


<h2 id="dotazniky">Feedback questionnaires</h2>

<p>Your opinion matters to us! Through your feedback we get comments and ideas that we love working with to improve GameCon and make it a better experience year after year.</p>

<ul>
<li><strong><a href="https://forms.gle/zkjgMmJXnxDVrPME7" target="_blank" rel="noopener noreferrer">General feedback</a></strong></li>
</ul>

<p>Don’t forget to rate the individual activities and game masters too. GameCon is a labour of love for all of us, so we’re always glad to hear what you liked, or what we can work on for next year.</p>

<ul>
<li><strong><a href="https://forms.gle/pTYdB9ncmz8FvWHK7" target="_blank" rel="noopener noreferrer">Action games and bonuses</a></strong></li>
<li><strong><a href="https://forms.gle/2v5cx5LK2Ynr43uq6" target="_blank" rel="noopener noreferrer">Celohra</a></strong></li>
<li><strong><a href="https://forms.gle/J7t1PjeTby6yzyG57" target="_blank" rel="noopener noreferrer">Board game library, tournaments and turbo tournaments</a></strong></li>
<li><strong><a href="https://forms.gle/iSd9WK6kScSjqoVb6" target="_blank" rel="noopener noreferrer">Epic board games</a></strong></li>
<li><strong><a href="https://forms.gle/KWfZePrkDVdQCJAw6" target="_blank" rel="noopener noreferrer">Larps</a></strong></li>
<li><strong><a href="https://forms.gle/5tbjsJYPkgA7FDEA6" target="_blank" rel="noopener noreferrer">Legends of the Adventurers’ Club</a></strong></li>
<li><strong><a href="https://forms.gle/TY8gM2JSmbSAB2Yh7" target="_blank" rel="noopener noreferrer">Talks</a></strong></li>
<li><strong><a href="https://forms.gle/3f6bJ7FfSvdFob9V9" target="_blank" rel="noopener noreferrer">RPG</a></strong></li>
<li><strong><a href="https://forms.gle/LR6FVdXqUpWdxtaX8" target="_blank" rel="noopener noreferrer">Wargaming and miniature painting</a></strong></li>
<li><strong><a href="https://forms.gle/Xk3feyBvJ6eWk1mz5" target="_blank" rel="noopener noreferrer">D&amp;D tournament</a></strong></li>
</ul>

<p>And if you’d like to help us make GameCon better from the inside as an organiser, we’ll be glad to have you on board! You’ll find more information and the positions we’re currently looking for <a href="https://gamecon.cz/organizacni-vypomoc">here</a>.</p>
MD;

$obsahEn['kontakty'] = <<<'MD'
<style>
    .flex-container {
        display: flex;
        flex-direction: column;
    }

    .kontakt {
        max-width: 56%;
        margin-right: 3em;
        margin-bottom: 1.25em;
        text-align: justify;
    }

    .adresy {
        max-width: 40%;
    }

    .adresy--polozka {
        margin-bottom: 5em;
    }

    .mail {
        font-size: 20pt;
        text-align: center;
        margin: 24px 0;
    }

    .flexible {
        flex-direction: row;
    }

    h4 {
        margin: 0;
    }

    .mezera {
        margin-top: 3em;
    }

    @media (max-width: 600px) {
        .kontakt,
        .adresy {
            max-width: 100%;
        }

        .flexible {
            flex-direction: column;
        }

        .adresy--polozka {
            margin-bottom: 3em;
        }

        .mail {
            font-size: 18pt;
        }
    }
</style>



<section id="kontakty-uvod">
    <h1>Contacts</h1>
    <div class="flex-container flexible">
        <article class="flex-container kontakt mezera">
            Got a question, something unclear or a problem? We’ll sort everything out at this e-mail address:
            <div class="mail">
                <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>
            </div>

           From Thursday 23 July, 11:00, you can also reach us by phone: <span>the <b>info desk:</b></span>
            <div class="mail">
                <a href="tel:+420735513899">+420 735 513 899</a>
            </div>

            <span>the <b>medic:</b></span>
            <div class="mail">
                <a href="tel:+420735540479">+420 735 540 479</a>
            </div>

            <span>and our <b>Kindred Spirit:</b></span>
            <div class="mail">
                <a href="tel:+420705943002">+420 705 943 002</a>
            </div>

You can also contact our Kindred Spirit by e-mail, which only our professional psychologist Michaela Hrabalová has access to.
            <div class="mail">
                <a href="mailto:spriznenaduseGC@gmail.com">spriznenaduseGC@gmail.com</a>
            </div>
        </article>
        <article class="flex-container adresy mezera">
            <div class="adresy--polozka">
                <h4>Payment and billing details:</h4>
                <b>GameCon z.s.</b><br />
                Pernického 825/9<br />
                199 00 Praha 9 Letňany <br />
                <br />
                <b>Company ID (IČO):</b> 22725474 <br />
                <b>Bank account (Czech Republic):</b> 2800035147/2010 <br />
                <b>Bank account (Slovakia):</b> 2800035147/8330 <br />
            </div>
            <div class="adresy--polozka">
                <h4>Venue:</h4>
                <b>Vysoká škola báňská - Technická univerzita Ostrava, Koleje Poruba</b> <br />
               Studentská 1770/1 <br />
                708 00 Ostrava-Poruba
            </div>

        </article>
    </div>
    <div style="text-align: justify;">
        <p>Game Masters are in touch with the heads of the individual programme sections; you’ll find their contacts on the page of each line. Institutions can contact the individual organisers directly, which makes the conversation quicker.
        </p>
    </div>
</section>

<section id="rada">
    <h2>GameCon Board</h2>
    <div class="medailonky">
        <!------- GANDALF -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/gandalf_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Petr “Gandalf” Holub</h3>
                <p>“chief organiser”</p>
                <p>Chair of the Board</p>
                <p><a href="mailto:petr.holub@holub-ak.cz">petr.holub@holub-ak.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I first went to GameCon in 1996 and since then I’ve practically earned my own inventory number. First as a GM, and in 2008 I took over as GameCon’s chief organiser. My kids then made me take a break from this “pastime”, and after it I took turns running the facilities or the finances (or both :)) until I once again earned a place at the head of the organising team.</p>
                    <p>These days I have much less time for playing during the year – mostly board games and the occasional RPG. But GameCon is still more than a hobby – it’s more of a labour of love. All those hours of work are worth it when GameCon goes well and turns into a great event that people love coming to.</p>
                </div>
            </details>
        </article>

        <!------- FLANT -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/flant_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Mirek “Flant” Dokoupil</h3>
                <p>head of Purchasing</p>
                <p>Vice-Chair of the Board</p>
                <p><a href="mailto:mirek.dokoupil@gamecon.cz">mirek.dokoupil@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I got to my first GameCon more or less by chance, in 2011. The main draw for me was board games – I didn’t get many chances to play them during the year, so I really enjoyed GameCon. But I also got interested in miniatures and wargaming, which I had a taste of too.
                    </p>

                    <p>As time went by and GameCon moved to Pardubice, I wanted to lend a hand and help as a volunteer. And somehow I ended up helping more and more, until I gradually went through several different positions in the organising team.
                    </p>
                </div>
            </details>
        </article>

        <!------- LILITH -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/lilith2026.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Marie “Lilith” Arleth</h3>
                <p>head of Operations and Venues</p>
                <p>Vice-Chair of the Board</p>
                <p><a href="mailto:zazemi@gamecon.cz">zazemi@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I first got to GameCon in 2021, in a slightly roundabout way: a friend from the Dračí doupě community asked if I’d like to join his party in the DrD Championship, and said I could bring more people along. I brought four friends… and nobody ever saw me at the DrD Championship. Instead, you could see me at larps, action games, RPGs and board game tournaments.</p>
                    <p>And when I saw the mega-sophisticated Excel spreadsheets for venues and operations, it was clear. Yep, and now I’m an organiser here.</p>
                </div>
            </details>
        </article>

        <!------- GERETE -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/gerete_2024.JPG" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Michal “Gerete” Bezděk</h3>
                <p>head of IT</p>
                <p>Board member</p>
                <p><a href="mailto:gerete@gamecon.cz">gerete@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>Ph'nglui mglw'nafh Cthulhu R'lyeh wgah'nagl fhtagn.</p>
                </div>
            </details>
        </article>

        <!------- DANČA -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/danca_2024.JPG" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Daniela “KeXa” Krejčí</h3>
                <p>head of Comms and Graphics</p>
                <p>Board member</p>
                <p><a href="mailto:info@gamecon.cz">info@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I’d been hearing about GameCon from a friend for several years, until in 2021 a group of friends and I decided we had to experience it first-hand. It didn’t even take a whole day for the atmosphere and the people to win me over completely, and GameCon became a labour of love. Larps grabbed me the most, and over time I also found my way to miniature painting.</p>

                    <p>The very next year, the same friends talked me into joining the organisers and fixing all those grammar mistakes that popped up here and there in the activity descriptions and other texts. I became the chief proofreader and copywriter, and a few years later I took over running the Comms and Graphics sections, where I make sure GameCon not only works well, but also looks and sounds good.</p>
                </div>
            </details>
        </article>

        <!------- JINDŘICH -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/jind%C5%99ich_2024.JPG" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Jindřich Mašek</h3>
                <p>head of programme and Epic Board Games</p>
                <p>Board member</p>
                <p><a href="mailto:epic@gamecon.cz">epic@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>Jindřich hasn’t written anything about himself yet.</p>
                </div>
            </details>
        </article>

        <!------- ARCHIMEDESFORT1 -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/archimedesfort1_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Ondřej “Archimedesfort1” Valášek</h3>
                <p>head of programme and Talks</p>
                <p>Board member</p>
                <p><a href="mailto:prednasky@gamecon.cz">prednasky@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I first set out for GameCon to hear a single Saturday talk and meet a few people from the RPG community; a year later I took part in the whole GameCon as one of the talk organisers. And GameCon cast its spell on me. Now I’m in charge of the talks.</p>

                    <p>Besides talks, I sometimes play board games, but mostly I play RPGs, and I’ve been very active in the RPG community for a few years now. I also like to try a larp or wargaming now and then.</p>

                    <p>I hope the talks at GameCon will thrive and that everyone will be happy with them – organisers, game masters and audience alike.</p>
                </div>
            </details>
        </article>
    </div>

</section>

<section id="finance">
    <h2>Head of Finance</h2>
    <div class="medailonky">
        <!------- SIRIEN -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/sirien_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Petr “Sirien” Mazák</h3>
                <p>head of Finance</p>
                <p><a href="mailto:finance@gamecon.cz">finance@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>My path to GameCon and into its organisation was long and complicated. What finally won me over was the perfect combination of different kinds of games, fun and parties, and a great community. Over many years I’ve gone through many positions in the GameCon organisation, all the way up to chairman, from where I’m now falling back down again. What matters is that GameCon still works and thrives despite my leadership, which speaks well of its quality and of the strength of the community behind it.</p>

                    <p>People often find it hard to tell when I’m joking and when I’m not. Several times my ironic jokes during the organisation were taken seriously by the others and actually put into practice. I’m not quite sure what that says about whom. In general, I’m more approachable than I might seem, so definitely don’t hesitate to talk to me (whether online during the year or right at GC).
                    </p>

                    <p>My “gaming centre of gravity” is and always has been RPGs, but over time I’ve developed a taste for pretty much every kind of game GC currently offers, especially board games and larps. Besides GC, I’ve organised a few other things in the past, and I’m one of the admins of the Czech RPG website <a href="http://www.d20.cz">Kostky</a>.
                    </p>
                </div>
            </details>
        </article>
    </div>
</section>


<section id="infopult">
    <h2>Head of the Info Desk</h2>
    <div class="medailonky">
        <!------- Don Cico -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/don_cico_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Petr “Don Cico” Čičák</h3>
                <p>head of the Info Desk</p>
                <p><a href="mailto:info@gamecon.cz">info@gamecon.cz</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>Peťa hasn’t written anything about himself yet.</p>
                </div>
            </details>
        </article>
    </div>
</section>

<section id="spriznena-duse">
    <h2>Kindred Spirit</h2>
    <div class="medailonky">
        <!------- Myška Hrabalka -------->
        <article class="medailonek">
            <img class="medailonek_fotka" src="soubory/obsah/obrazky/organizatori/eruvielle_2024.jpg" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">Michaela “Eruvielle” Hrabalová</h3>
                <p>Kindred Spirit</p>
                <p><a href="mailto:spriznenaduseGC@gmail.com">spriznenaduseGC@gmail.com</a></p>
            </div>
            <details class="medailonek_vice">
                <summary>in their own words</summary>
                <div class="medailonek_text">
                    <p>I first heard about GameCon one evening from friends while playing Arkham Horror. Of course, their stories were so captivating that I wanted to join in. However, through some intervention of the Old Ones, I ended up in the organising team as the Kindred Spirit. The whole atmosphere of the festival captivated me so much that I developed separation anxiety from the whole community and the organising team. Even after three years it hasn’t let go of me, and ever since I’ve been coming (regularly) to this awesome festival.</p>
                </div>
            </details>
        </article>
    </div>
</section>
MD;

$obsahEn['legal'] = <<<'MD'
# Information on the processing of personal data

Dear participant, by submitting your registration you show that you are interested in taking part in the GameCon festival (the “**Festival**”). For this reason, we need to process your personal data. This short text serves as basic information, in which you will learn everything that matters.

Your personal data provided in the registration are processed by us, i.e. the registered association (zapsaný spolek) GameCon, with its registered office at Pernického 825/9, 199 00, Praha 9 Letňany, Company ID (IČ) 227 25 474 (“**GameCon**”), for the purpose of your participation in events organised by the GameCon association. The legal basis for this processing is the performance of the contract concluded between us (i.e. your registration for the Festival).

In other words, your personal data provided in the registration are necessary for the Festival to run properly and successfully – for example, we will use your personal data to create lists of players, or to contact you before a game (if you are interested in the legal details, this is Article 6(1)(b) of the General Data Protection Regulation No. 2016/679, hereinafter the “GDPR”). We may pass your personal data on to third parties, such as the specific people running activities, or accommodation facilities. Again, this will happen only on the basis of your registration and the requests you make in it. A precise list of the third parties is available to you free of charge on request sent to the e-mail address <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.

By approving these terms, you consent to your personal data being kept between the individual years of the Festival. This is consent to the processing of your personal data, given for an indefinite period, for the purpose of keeping your participant profile, so that you do not have to create it again for future years. You may withdraw this consent at any time free of charge by a written request delivered to the e-mail address <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>. If you withdraw your consent, we will anonymise your personal data in our database and, for the purpose of keeping statistics, we will retain only such data that cannot be used to identify you (however, the withdrawal of consent does not apply to data necessary for running GameCon or to data that we are required by law to keep for a certain period).

For both cases of personal data processing mentioned above, you may contact us at any time (again at <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>) if you would like access to your personal data (under Article 15 of the GDPR), if you would like your personal data rectified or erased (under Articles 16 and 17 of the GDPR), or if you would like to restrict the processing of your personal data (Article 18 of the GDPR).

We would also like to inform you that a few times a year we will send you our Newsletter with information about future years of the Festival, or other offers. We may do so because you provided your e-mail address when registering for the Festival (again, if you are interested in the legal details, this is Section 7(3) of the Czech Act No. 480/2004 Coll., on Certain Information Society Services). You can refuse to receive our Newsletter at any time by writing to the e-mail address <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>, or by clicking the link directly in the e-mail.

Thank you, and that is all. We look forward to seeing you at the Festival!
MD;

$obsahEn['rpg/jak-si-vybrat-hru'] = <<<'MD'
# How to choose a game

![](soubory/obsah/rpg/jak-si-vybrat-hru.jpg)


Choose carefully – not just for your own sake
---------------------------------------------

Playing at GC has its own specifics. Besides those given by the format
of the event (time, rooms…), they include the fact that players often
don’t know the game master, the other players, or even the system or
setting they are going to play – and that’s a good thing! GameCon is
here (among other things) so that you can try something new,
unconventional or unusual, play with someone new and see how other
people play.

Maybe you know in advance what you want to play and you’re just looking
for it. Or maybe you’ve signed up for something because it caught your
eye (or because your friend is going, because you want to fill a free
slot in your programme, etc.). **Either way, it’s good to have the best
possible idea of what kind of game it is**: in the first case so that
you really get what you’re looking for, in the second so that you know
in advance what to expect. It’s good for the other players too, because
in an RPG the fun is created together, so when someone ends up not
enjoying the game, everyone feels it.

Even if you choose well, don’t forget that RPGs (like the rest of GC)
are interactive: the fun doesn’t come by itself, you and your fellow
players create it too. Interesting things come out of working together
and combining what everyone brings into the game, so remember to be
open to how the others see things, and don’t be afraid to ask directly
if needed (ideally as early as possible, as soon as you have any doubts).

To help you choose as well as possible (or get the most accurate idea
in advance), we offer you several ways to find your way around the
games:

Description
-----------

The description of a game tells you what the game is and what it will
be about. It has three parts, each of which gives you slightly
different information about the game:

**The first paragraph** of the description sets the **tone** and says
something about the atmosphere, genre and style of the game. What
matters is not only what is written in it, but also how it is written.
Of course, we don’t necessarily pretend to be world-class writers, but
even so, this paragraph should show you what the game will feel like.

**The second paragraph** is written more directly and covers the
**actual content of the game**: what exactly will be dealt with in it,
and possibly how, what elements will be present, which aspects of play
it will focus on and so on.

**The third paragraph** briefly introduces the system (and the setting,
if it is in any way specific) that the game uses.

**The fourth paragraph** may or may not be there; it may contain
additional information, links to the rules (for those interested, you
*don’t need* to know them unless exceptionally stated otherwise), to
short materials to read before the game, etc.

Tags
----

You’ll find tags in the header of an activity; they give short keyword
information about various things related to the game.

It may be information you’ll also find in the description, which the
tag emphasises even more. If an evening game with a horror description
has the *Horror* tag, expect the game to focus really strongly on
horror.

The other possibility is that it’s some specific information. Don’t be
confused if a tag means nothing to you! Such tags are meant to help
people who know what it is and are looking for it to find their way
quickly (so that they see it straight away and don’t have to search the
descriptions), but if you’re not one of them, it shouldn’t put you off.
Typically these may be abbreviations of the systems used (WoD,
GUMSHOE, WHFR…).

Game Masters
------------

And last but definitely not least, we’re fully aware that how much fun
a game is depends to a large extent on the “chemistry” between the
players themselves. While we can’t give you profiles of your fellow
players, we can at least partly introduce the game master running the
game.

If you click on a game master’s name, the website takes you to their
profile page. It contains a photo, but above all a short personal and
gaming introduction that tells you something about who they are and how
they approach roleplaying.

And of course, the profile also lists all the activities the
game master runs at GC, so if you had a great game with someone the
year before, this is an easy way to find the games they’re running this
year.

**If you’re unsure about anything in a game, don’t be afraid to ask.**
You can ask its game master privately, you can ask the head of the RPG
line, or you can ask on our Facebook. Either way, you’ll surely get an
answer, and it can make the game a lot clearer, not only for you but
for the others too.

In general, we encourage game masters to talk to people about their
games before GC (usually by e-mail), because we’ve found that it helps
mutual understanding a lot. So please, if needed, take a moment to
reply; a game of 4 hours or more is definitely worth those few minutes.
MD;

$obsahEn['rpg/jak-si-hrani-co-nejvice-uzit'] = <<<'MD'
# How to enjoy playing as much as possible

![](soubory/obsah/rpg/jak-si-hrani-co-nejvice-uzit.jpg)


We need your help!
------------------

For many years we’ve been systematically trying to improve the whole
line as well as the individual games in it. We collect extensive
feedback, take lots of notes ourselves, and every year there was
something you criticised that we did better the following year. There
has been a lot of your criticism over the years and we were glad of it,
because thanks to it the line got better and better every year.

Recently, however, we ran into a problem. For games with lower ratings,
the reasons given were no longer problems on the side of the
game masters or of us organisers; more and more often they were
problems with players who didn’t get on with each other in the game.
The players at GC don’t seem to have changed much, so we were pleased
that we had gradually got rid of all the complaints aimed at us. On the
other hand, it left us with a problem: how do we keep improving the
games when the key to improving them further isn’t in our hands?

We all want to have as much fun as possible at GC and play the best
games possible, but the next step towards improvement now apparently
isn’t on our side any more. It’s on yours, the players’.

If you want to have fun, choose
-------------------------------

Every game is different, and GC offers a very wide range of games, both
in terms of systems and of the styles they’re played in. Everyone also
enjoys playing something different, and there’s something everyone
doesn’t enjoy. Players sometimes pick a different game than they’d have
liked by mistake, which is why this year we’ve focused on making your
own choice easier and better:

We’ve polished the descriptions further so you can learn as much as
possible from them. The tags, which until now only helped you find your
way around a few things (systems), are now used more broadly and also
tell you about the style the game will focus on. And last but not
least, we keep working on our game masters’ profiles.

Please pay attention to your choice. When you know what you want, it
makes it easier to actually find it. When you’re trying something new,
it gives you a much better idea in advance of what it will be about.
And if you’re going to a game because of your friends, or simply
because you happen to have a free slot, it tells you what to “tune in”
to.

From plenty of our own and GameCon experience we know that
expectations matter a great deal, and that the same game can leave a
“super” or a “meh” impression just depending on how you approach it at
the start and how far it ends up matching what you expected, so
definitely don’t neglect your choice.

As we’ve already said, many (last year even most) of the games with
lower ratings in the feedback weren’t bad games in themselves, but
games where, unfortunately, players met who didn’t get on with each
other. Of course, not everyone always necessarily “clicks”, but
sometimes it isn’t about personal chemistry, but about things that can
be influenced:

Communicate
-----------

Firstly, when you choose games carefully, there’s less chance that
people who expect something completely different will meet at the same
game. Of course, there’s still room for different interpretations of
many things; we try to eliminate that by communicating about the game
before GC. Both experience and feedback show that communication about
the game before GC between the players and the game master (and among
the players themselves) helps a lot to create better and more enjoyable
games.

**If you expect / want something specific from a game**, say so,
ideally to the other players too, but at least to the game master. If
you’ve chosen the game for a specific reason (e.g. you want to try the
system, or even something specific in that system), say it. Firstly,
the game master can adapt the game so you really get it; secondly, the
others will know that’s what you’re after. If, on the other hand,
there’s something you don’t want in the game (e.g. you chose it
precisely because it looks like it won’t deal with rules much), say
that too, maybe even more so. There’s only one game, and if the
players’ preferences differ in some way, its game master will make
clear which way the game leans.

**If you’re not sure about something**, ask about it. Seriously. It
sounds trivial, but people often feel all kinds of reluctance to
approach someone and ask, so please get over it. You can ask the
game master, the head of the RPG section or, for example, on the GC
Facebook page; either way, that link between question and answer is
important and really helps.

**Don’t be afraid to communicate during the game too**. If something
isn’t clear to you, don’t be afraid to ask. More importantly: if you
find during the game that it’s heading somewhere a little different
from what you’d enjoy, say so at a suitable moment. Whether you’d like
to add a bit of action, or on the contrary slow down a bit and play out
more interactions between the characters, or you feel the game could
pick up the pace a little, don’t hesitate to say it. Game Masters are
only human too and don’t always run everything perfectly. A short
discussion like this about the state of the game and where it’s heading
is certainly not an attack on the game master; on the contrary, it’s
taking part in the game and in making it as much fun as possible for
everyone. Of course, the players’ ideas may differ, but you won’t
clear that up without talking about it. One thing always applies:

Play with the others, not just with yourself
--------------------------------------------

Every now and then it happens to anyone: you sign up for a game you
expect something from, you’re really looking forward to it, and then at
the game you meet players who see it a bit differently. It doesn’t have
to be anyone’s fault; what’s intended and what actually gets played are
never quite the same thing. The problem comes when one side starts
blindly pushing its own idea and ignoring how the others see the game.

RPGs don’t have a form set in stone that has to be filled. One and the
same scenario played by different groups always creates different
stories that focus on different things the game offers. What the game
ends up looking like is the sum of what everyone puts into it together.
When different people favour different things, it does no harm. Quite
the opposite: the experience of the whole gaming community says that
the best games come about precisely when different players bring out
different themes. But for that to work, players mustn’t play
“blindly”. Even when they favour their own thing, for it to benefit the
game they still need to be aware of the others, both in the sense of
reflecting the other themes in the game and in the sense of recognising
when to step back into the background with their own theme for a while.

We write “theme” here, but it doesn’t have to concern only in-game
(story) themes. The same goes for different approaches to the game
(more rules / more drama) or to demo play itself (more explaining /
more free fun). Let us refer back to what we’ve already written: don’t
be afraid to communicate. If you feel that intuition is failing, be it
in other players or in yourself, don’t be afraid to start a short
exchange about how to carry on with the game.

The fun in an RPG is essentially synergy: the more the individual people
enjoy themselves, the more everyone enjoys themselves and the better the
game is. That is, the more you enjoy yourself, the more your fellow
players can enjoy themselves too, but you’ll also enjoy yourself more
if you let yourself be influenced by how (and with what) the others are
having fun.
MD;

$obsahEn['larpy/bozi-larp'] = <<<'MD'
# How to play an awesome larp

![](soubory/obsah/larpy/bozi-larp.jpg)


Think about what kind of game would appeal to you.
--------------

- Serious or light-hearted, relationship-driven or debate-driven, adventurous or comedic, improvised or scripted? Chances are that not every larp GC offers will suit you, but thanks to the descriptions you can choose the one whose theme and style speak to you.

- If your dream larp is already full, don’t be sad, just click the **“sledovat” (watch) button** next to the game. You can even watch several games at the same time. As soon as a place frees up, the system will send you an e-mail and you’ll be able to sign up (careful: the system won’t sign you up automatically, so you need to be quick).

- **Larp plague** (a serious infectious disease with a significant impact on society; source: wikipedia.org): a common illness affecting the player population shortly before a gaming event, causing places to free up in otherwise completely unavailable games. If you’re watching for free places, please follow point 6. Stay reachable: we may call you at the start of a block to say a place has suddenly freed up, and we’ll treat you like royalty if you pick up the phone and come to play. Win-win.

Talk to your game master.
------------

- Before GameCon, your game master will send you an e-mail with information about the game and what costume is suitable, and may ask whether you’d rather play Hansel or Gretel (or maybe other characters, if there are any in the game). Please reply as soon as possible. If you have questions about the game or aren’t sure you’ve chosen well, take the chance and ask.

Get your costume ready.
--------

- There’s probably no larp that wouldn’t be better with a costume than without, although many of them can be played in everyday clothes too. Don’t worry that you won’t find anything suitable in your wardrobe at home: even a shirt and black trousers create a completely different atmosphere from a printed T-shirt and shorts. Because what? Atmosphere is the foundation of an awesome larp!

- If you’re watching for free places in several games, or suspect you’ll want to play a larp if something frees up, you basically can’t go wrong by bringing a shirt and trousers, or a blouse and a skirt, to GameCon; this costume “basis” works for almost any larp.

- If you come to a game as a substitute and don’t have a costume with you, don’t worry: this year we’ll have some costumes in stock, and if needed, we’ll step in and lend you something.

Don’t cancel! And if you have to, please do it as soon as possible.
-------------

- Most larps need a full number of players to take place. So if at the last minute you swap a larp for a ball-juggling workshop, it’s a big problem for us (even though we understand juggling is great). It’s not at all certain that someone will sign up for your place at the last minute, and without you, i.e. with an incomplete cast, the game can’t be played.

- We understand there are situations when you have to cancel, but then please do so as soon as you find out you can’t play. It greatly increases the chance that someone else will still sign up for your place. The unhappiest situation, which unfortunately still happens, is when you can’t come but don’t cancel in the system. Hunting for substitutes at the start of a game block is a really annoying and stressful job for us. If you can spare us that, we’ll love you and organise only the best larps in the world for you. =)

Come to the meeting point on time.
--------

- It may seem that a little delay at the coffee stand (we know, there’s often a hellish queue) doesn’t matter, but believe us, in the packed GameCon programme every 5 minutes counts. At the very least, it’s 5 minutes of stomach ulcers growing for the game master, who is already ringing every phone line in a panic that a player is missing and the game won’t be playable.

Stay reachable
----------

- If you’re signed up for a larp or watching for free places, please stay reachable on the phone number you gave when registering. If you get held up at the coffee stand (which will certainly never happen to you any more, as you’ve read point 5), or if someone can’t make it to the game and a place opens up for a substitute, we’ll call you! Be ready for it. It’s frustrating to have 5 substitutes on the list and not be able to reach any of them.

Listen to the game master
------

- The game master is there to prepare you for the game you’re about to play. They’ll tell you how to handle information in the game, how interactions between characters work and how to react in specific situations. You may even try out some mechanics or situations in pre-game workshops. Pay proper attention to this preparation, even if you feel you’ve heard it a thousand times. Every game is a bit different.
- **Ask before the game**. Is something unclear to you already during the briefing? Ask. There are no stupid questions.

And now let’s play!
--------

- **Be active**. No awesome larp has ever been played with everyone sitting in a corner and not interacting with anyone.
- **Read your character carefully**: everything written in your character sheet is important! Secrets, information and possibly items are usually meant as charges that set the game in motion. So the real “goal” usually isn’t to keep them at all costs; it can be much more interesting to release them into the world at the right moment. When the right moment comes depends on you as a player, on your character and on how that particular game unfolds.
- Remember that **you’re making the game awesome not just for yourself, but for the other players too**. If you’re supposed to deal with three characters in the game and you decide to ignore one of them completely, that player may lose an important part of their game.
- If you’re lost during the game, bored or don’t know what to do, **ask the game master**. They’ll be happy to point you in the right direction or advise you what to do next or which storyline you could still get going. It’s best to ask somewhere out of the way, ideally so that the other players can’t hear.
- **Don’t step out of character**. In a chamber larp, speaking out of character isn’t customary; you should sort everything out “in character”. If another player asks you something that isn’t written in your character sheet but your character would probably know (e.g. “Where did you go to primary school?”), feel free to make something up; if it isn’t written in your character sheet, it’s probably not important. Likewise, if other players make up some information in the game, play along as if it were true. E.g. if someone says “Hey, remember that time we went fishing together?” and you have no idea what they’re on about, take it into the game and throw in something like “Oh yeah, you mean when we caught that huge pike?”, but you could just as well say “Come off it, that must have been with one of your floozies, you and your memory!” It’s up to you what fits the game better at that moment.

Thanks for reading all the way to the end, and we’re looking forward to all the awesome games we’ll play with you!

Password: Scio me nihil scire.
MD;

$retezec = fn (string $text): string => "'" . $this->connection->real_escape_string($text) . "'";
foreach ($obsahEn as $urlStranky => $obsah) {
    $this->q(<<<SQL
UPDATE stranky
SET obsah_en = {$retezec($obsah)}
WHERE url_stranky = {$retezec($urlStranky)}
SQL,
    );
}
