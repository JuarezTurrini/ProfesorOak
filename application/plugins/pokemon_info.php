<?php

function pokemon_attack($text, $target_attack = FALSE){
	$pokemon = new Pokemon();
	$types = $pokemon->attack_types();

	// $this->last_command("ATTACK");
	$str = "";

	if(strpos(strtolower($text), "missing") !== FALSE){
		return "Lo siento, no encuentro ese número. Es que me parece que se ha perdido.";
	}elseif(trim(strtolower($text)) == "mime"){
		$text = "Mr. Mime";
	}elseif($text[0] == "#" && is_numeric(substr($text, 1))){ // Si es número pero con #
		$text = substr($text, 1);
	}

	// $attack contiene el primer tipo del pokemon
	$pk = $pokemon->find($text);
	if($pk !== FALSE){
		$str .= "#" .$pk['id'] ." - *" .$pk['name'] ."* (*" .$types[$pk['type']] ."*" .(!empty($pk['type2']) ? " / *" .$types[$pk['type2']] ."*" : "") .")\n";
		$primary = $pk['type'];
		$secondary = $pk['type2'];
	}else{
		$str .= (!$target_attack ? "Debilidad " : "Fortaleza ");
		if(strpos($text, "/") !== FALSE){
			$text = explode("/", $text);
			if(count($text) != 2){ return NULL; } // Hay más de uno o algo raro.
			$primary = trim($text[0]);
			$secondary = trim($text[1]);

			$str .= "*" .ucwords($primary) ."* / *" .ucwords($secondary) ."*:\n";
		}else{
			$primary = $text;
			$str .= "*" .ucwords($primary) ."*:\n";
		}

		$primary = $pokemon->attack_type($primary); // Attack es toda la fila, céntrate en el ID.
		if(empty($primary)){
			return NULL;
			// $this->telegram->send("Eso no existe, ni en el mundo Pokemon ni en la realidad.");
		}
		$primary = $primary['id'];

		if(!empty($secondary)){
			$secondary = $pokemon->attack_type($secondary);
			if(!empty($secondary)){ $secondary = $secondary['id']; }
		}
	}

	// $table contiene todos las relaciones donde aparezcan alguno de los dos tipos del pokemon
	$table = $pokemon->attack_table($primary);
	$target[] = $primary;
	if($secondary != NULL){
		$table = array_merge($table, $pokemon->attack_table($secondary));
		$target[] = $secondary;
	}

	// debil, muy fuerte
	// 0.5 = poco eficaz; 2 = muy eficaz
	$list = array();
	$type_target = ($target_attack ? "target" : "source");
	$type_source = ($target_attack ? "source" : "target");
	foreach($table as $t){
		if(in_array(strtolower($t[$type_source]), $target)){
			if($t['attack'] == 0.5){ $list[0][] = $types[$t[$type_target]]; }
			if($t['attack'] == 2){ $list[1][] = $types[$t[$type_target]]; }
		}
	}
	foreach($list as $k => $i){ $list[$k] = array_unique($list[$k]); } // Limpiar debilidades duplicadas
	$idex = 0;
	foreach($list[0] as $i){
		$jdex = 0;
		foreach ($list[1] as $j){
			if($i == $j){
				// $i y $j contienen el mismo tipo, hay contradicción
				unset($list[0][$idex]);
				unset($list[1][$jdex]);
			}
			$jdex++;
		}
		$idex++;
	}

	if(isset($list[0]) && count($list[0]) > 0){ $str .= (!$target_attack ? "Apenas le afecta *" : "Apenas es fuerte contra *") .implode("*, *", $list[0]) ."*.\n"; }
	if(isset($list[1]) && count($list[1]) > 0){ $str .= (!$target_attack ? "Le afecta mucho *" : "Es muy eficaz contra *") .implode("*, *", $list[1]) ."*.\n"; }

	return $str;
}

function pokemon_basic_info($poke, $obj = NULL){
	// Mostrar
	if((is_numeric($poke) or is_string($poke)) && $obj != NULL){
		$poke = $obj->pokedex($poke);
	}elseif(!is_object($poke) && $obj === NULL){
		return FALSE;
	}

	// TODO Devolver string con el texto de información del Pokémon.
	return "";
}

function pokemon_movements($poke, $obj = NULL){

}

function pokemon_level_multiplier($stardust, $powered = FALSE){

}

function pokemon_iv($pokeobj, $cp, $hp, $stardust, $extra = NULL){

}

function pokemon_seen($user, $poke, $loc, $cooldown = 60){
	$pokemon = new Pokemon();
	$telegram = new Telegram();
	// $pk = $pokemon->settings($user->id, 'pokemon_select');

	// $pokemon->settings($user->id, 'pokemon_select', 'DELETE');
	// $pokemon->settings($user->id, 'step_action', 'DELETE');

	if($cooldown !== FALSE){
		$cd = $pokemon->settings($user, 'pokemon_cooldown');
		if(!empty($cd) && $cd > time()){
			$telegram->send->text("Aún no ha pasado suficiente tiempo. Espera un poco, anda. :)");
			$pokemon->step($user, NULL);
			return -1;
		}
	}

	if($pokemon->user_flags($user, ['troll', 'rager', 'bot', 'forocoches', 'hacks', 'gps', 'trollmap'])){
		$telegram->send->text("nope.")->send();
		$pokemon->step($user, NULL);
		return -1;
	}

	if(!is_array($loc)){
		$loc = explode(",", $loc); // FIXME cuidado con esto, si reusamos la funcion.
	}
	$pokemon->add_found($poke, $user, $loc[0], $loc[1]);

	// SELECT uid, SUBSTRING(value, 1, INSTR(value, ",") - 1) AS lat, SUBSTRING(value, INSTR(value, ",") + 1) AS lng FROM `settings` WHERE LEFT(uid, 1) = '-' AND type = "location"

	$pokemon->settings($user, 'pokemon_cooldown', time() + $cooldown);
	$pokemon->step($user, NULL);

	// $this->analytics->event("Telegram", "Pokemon Seen", $poke);
	$telegram->send
		->text("Hecho! Gracias por avisar! :D")
		->keyboard()->hide(TRUE)
	->send();
	return TRUE;
}

// ---------------

$help = NULL;

if($telegram->text_contains(["añadir", "agreg", "crear", "solicit", "pedir"]) && $telegram->text_has(["paradas", "pokeparadas"])){
    $help = "Lo siento, pero por el momento no es posible crear nuevas PokéParadas, tendrás que esperar... :(";
}elseif($telegram->text_contains(["Niantic", "report"]) && $telegram->text_has(["link", "enlace", "página"])){
    $this->analytics->event("Telegram", "Report link");
    $help = "Link para reportar: https://goo.gl/Fy9Wt6";
}elseif($telegram->text_has(["poli", "polis", "policía"]) && $telegram->text_contains(["juga", "movil", "móvil"])){
    $help = "Recuerda que jugar mientras conduces el coche o vas en bicicleta, está *prohibido*. "
            ."Podrías provocar un accidente, así que procura jugar con seguridad! :)";
}elseif($telegram->text_has(["significa", "quiere decir", "qué es"]) && $telegram->text_contains(["L1", "L2", "L8"])){
    $help = "Lo del *L1* es *Level 1* (*Nivel*). Si puedes, dime tu nivel y lo guardaré.\n_(Soy nivel ...)_";
}elseif($telegram->text_has(["sombra", "aura", "fondo"], "azul")){
    $help = "La sombra azul que aparece en algunos Pokémon, es porque los has capturado en las últimas 24 horas.";
}elseif($telegram->text_has("espacio") && $telegram->text_has("mochila")){ // $telegram->text_contains(["como", "cómo"]) &&
    $help = "Tienes una mochila en la Tienda Pokemon, así que tendrás que buscar PokeMonedas si quieres comprarla. Si no, te va a tocar hacer hueco...";
}elseif($telegram->text_has(["normas", "reglas"]) && $telegram->text_has(["entrenador"]) && $telegram->words() <= 12){
    $help = "*Normas de Entrenador de Pokémon GO*\n"
            ."Pokémon GO es adecuado para jugar en un dispositivo móvil y ¡te lleva fuera, a explorar tu mundo! "
            ."Por desgracia, el único límite de las trampas que pueden realizarse es la imaginación de los tramposos, pero incluyen lo siguiente:\n"
            ."- Usar *software modificado o no oficial*\n"
            ."- Jugar con *múltiples cuentas* (una cuenta por jugador, por favor)\n"
            ."- Compartir cuentas\n"
            ."- Usar *herramientas* o técnicas para *alterar o falsificar tu ubicación*, o\n"
            ."- *Vender y comerciar* con las cuentas.\n"
            ."Más info: http://goo.gl/KowHG8";
    $telegram->send->disable_web_page_preview(TRUE);
}elseif($telegram->text_has(["cuáles son"]) && $telegram->text_has("legendarios")){
    $help = "Pues según la historia, serían *Articuno*, *Zapdos* y *Moltres*. Incluso hay unos Pokemon que se sabe poco de ellos... *Mew* y *Mewtwo*...";
}elseif($telegram->text_has(["preséntate"]) && $telegram->text_has(["profe", "profesor", "oak"])){
    $help = "¡Buenas a todos! Soy el *Profesor Oak*, programado por @duhow.\n"
            ."Mi objetivo es ayudar a todos los entrenadores del mundo, aunque de momento me centro en España.\n\n"
            ."Conmigo podréis saber información sobre los Pokémon, cuáles son los tipos de ataques recomendados para debilitarlos rápidamente, y muchas más cosas, "
            ."como por ejemplo cómo evolucionar a ciertos Pokémon o ver información de entrenadores, para saber de qué equipo son.\n\n"
            ."Para poder hablar conmigo, tengo que saber de qué equipo sois, bastará con que digáis *Soy rojo*, *azul* o *amarillo*. "
            ."Pero por favor, sed sinceros y nada de bromas, que yo me lo tomo muy en serio.\n"
            ."Una vez hecho, podéis preguntar por ejemplo... *Debilidad contra Pikachu* y os enseñaré como funciona.\n"
            ."Espero poder ayudaros en todo lo posible, ¡muchas gracias!";
/*
}elseif(
    ($telegram->text_has(["lista", "ayuda", "ayúdame", "para qué sirve"]) && $telegram->text_has(["comando", "oak", "profe", "profesor"]) && !$telegram->text_contains("nido")) or
    $telegram->text_command("help")
){
    if($telegram->is_chat_group() && $telegram->user->id != $this->config->item('creator')){
        $q = $telegram->send->chat( $telegram->user->id )->text("*Ayuda del Oak:*", TRUE)->send();
        $strhelp = ($q == FALSE ? "No puedo enviarte la ayuda, escríbeme por privado primero." :
                    "Te la envío por privado, " .$telegram->user->first_name .$telegram->emoji("! :happy:") );
        if($q == FALSE){
            $telegram->send
                ->inline_keyboard()
                    ->row_button("Ayuda de comandos", "/help", TRUE)
                ->show();
        }
        $telegram->send
            ->notification(FALSE)
            ->text($strhelp)
        ->send();
        $telegram->send->chat( $telegram->user->id ); // Volver a forzar
    }
    $this->analytics->event('Telegram', 'Help');
    $help = "- Puedes preguntarme sobre la *Debilidad de Pikachu* y te responderé por privado.\n"
            ."O si me pides que diga la *Debilidad de Pidgey aquí*, lo haré en el chat donde estés.\n"
            ."También puedes preguntar *Evolución de Charizard* y te diré las fases por las que pasa.\n"
            ."- Para juegos de azar, puedo *tirar los dados* o jugar al *piedra papel tijera*!\n"
            ."- Si os va lento el juego o no conseguís ver o capturar Pokemon, podéis preguntar *Funciona el Pokemon?*\n"
            ."- Podéis ver la *Calculadora de evolución* para saber los CP que necesitáis o tendréis para las evoluciones Pokemon.\n"
            ."- También tenéis el *Mapa Pokemon* con los sitios indicados para capturar los distintos Pokemon.\n"
            ."- Podéis preguntar *Quien es @usuario* (de Pokemon) para saber su equipo.\n"
            ."- Si mencionáis a *@usuario* (de Pokemon), le enviaréis un mensaje directo - por si tiene el grupo silenciado, para darle un toque.\n\n"
            ."¡Y muchas más cosas que vendrán próximamente!\n"
            ."Cualquier duda, consulta, sugerencia o reporte de problemas podéis contactar con *mi creador*. :)";
*/
}elseif(
    !$telegram->text_contains("http") && // Descargas de juegos? No, gracias
    $telegram->text_has(["descarga", "enlace", "actualización"], ["de pokémon", "pokémon", "del juego"]) &&
    $telegram->words() <= 12
){
    $google['web'] = "https://play.google.com/store/apps/details?id=com.nianticlabs.pokemongo";
    $apple['web'] =  "https://itunes.apple.com/es/app/pokemon-go/id1094591345";
    $web = file_get_contents($google['web']);
    $google['date'] = substr($web, strpos($web, "datePublished") - 32, 100);
    $google['version'] = substr($web, strpos($web, "softwareVersion") - 32, 100);
    foreach($google as $k => $v){
        $google[$k] = substr($google[$k], 0, strpos($google[$k], "</div>") + strlen("</div>"));
        $google[$k] = trim(strip_tags($google[$k]));
    }

    $web = file_get_contents($apple['web']);
    $apple['date'] = substr($web, strpos($web, "datePublished") - 16, 100);
    $apple['version'] = substr($web, strpos($web, "softwareVersion") - 16, 100);
    foreach($apple as $k => $v){
        $apple[$k] = substr($apple[$k], 0, strpos($apple[$k], "</span>") + strlen("</span>"));
        $apple[$k] = trim(strip_tags($apple[$k]));
    }

    $google['date'] = str_replace("octobre", "october", $google['date']); // FIXME raro de Google.

    $google['date'] = date("Y-m-d", strtotime($google['date']));
    $apple['date'] = date_create_from_format('d/m/Y', $apple['date'])->format('Y-m-d'); // HACK DMY -> YMD

    $google['days'] = floor((time() - strtotime($google['date'])) / 86400); // -> Days
    $apple['days'] = floor((time() - strtotime($apple['date'])) / 86400); // -> Days

    $google['new'] = ($google['days'] <= 1);
    $apple['new'] = ($apple['days'] <= 1);

    $google['days'] = ($google['days'] > 365 ? "---" : $google['days']);
    $apple['days'] = ($apple['days'] > 365 ? "---" : $apple['days']);

    $dates = [0 => "hoy", 1 => "ayer"];

    $google['web'] = "https://play.google.com/store/apps/details?id=com.nianticlabs.pokemongo"; // HACK la URL se pierde ._. por eso se vuelve a agregar
    $apple['web'] =  "https://itunes.apple.com/es/app/pokemon-go/id1094591345";

    $str = "[iOS](" .$apple['web'] ."): ";
    if($apple['new']){ $str .= "*NUEVA* de " .$dates[$apple['days']] ."! "; }
    else{ $str .= "de hace " .$apple['days'] ." dias "; }
    $str .= "(" .$apple['version'] .")\n";

    $str .= "[Android](" .$google['web'] ."): ";
    if($google['new']){ $str .= "*NUEVA* de " .$dates[$google['days']] ."! "; }
    else{ $str .= "de hace " .$google['days'] ." dias "; }
    $str .= "(" .$google['version'] .")";

    $telegram->send
        ->disable_web_page_preview(TRUE)
        ->inline_keyboard()
            ->row()
                ->button("Apple", $apple['web'])
                ->button("Android", $google['web'])
            ->end_row()
        ->show();
    $help = $str;
}elseif($telegram->text_contains(["recompensa", "recibe", "consigue", "obtiene"]) && $telegram->text_has(["llegar", "nivel", "lvl", "level"]) && $telegram->words() <= 10){
    $items = $pokemon->items();
    $num = filter_var($telegram->text(TRUE), FILTER_SANITIZE_NUMBER_INT);
    if($num > 1 && $num <= 40){
        $this->analytics->event('Telegram', 'Trainer Rewards', $num);
        $rewards = $pokemon->trainer_rewards($num);
        if(!empty($rewards)){
            // if($this->is_shutup()){ $telegram->send->chat($telegram->user->id); }
            $telegram->send->chat($telegram->user->id); // TODO
            $help = "En el *nivel $num* conseguirás:\n\n";
            foreach($rewards as $r){
                $help .= "- " .str_pad($r['amount'], 2, "0", STR_PAD_LEFT) ."x " .$items[$r['item']] ."\n";
            }
        }
    }
}elseif($telegram->text_contains("mejorar") && $telegram->text_has(["antes", "después"]) && $telegram->text_has(["evolución", "evolucionar", "evolucione"])){
    $help = "En principio es irrelevante, puedes mejorar un Pokemon antes o después de evolucionarlo sin problemas.";
}elseif($telegram->text_has(["calculadora", "calcular", "calculo", "calcula", "tabla", "pagina", "xp", "experiencia"]) && $telegram->text_has(["evolución", "evoluciona", "evolucione", "nivel", "PC", "CP"]) && !$telegram->text_contains(["IV", "lV"])){
    $help = "Claro! Te refieres a la Calculadora de Evolución, verdad? http://pogotoolkit.com/";
}elseif($telegram->text_has(["PC", "estadísticas", "estados", "ataque"]) && $telegram->text_has(["pokémon", "máximo"]) && !$telegram->text_contains(["mes"])){
    $help = "Puedes buscar las estadísticas aquí: http://pokemongo.gamepress.gg/pokemon-list";
}elseif($telegram->text_has(["mapa", "página"]) && $telegram->text_has(["pokémon", "ciudad"]) && !$telegram->text_contains(["evoluci", "IV", "calcul"])){
    $this->analytics->event('Telegram', 'Map Pokemon');
    // $help = "https://goo.gl/GZb5hd";
}elseif($telegram->text_has(["evee", "evve"]) && !$telegram->text_has("eevee") && $telegram->words() >= 3){
    $help = "Se dice *Eevee*... ¬¬";
}elseif($telegram->text_has(["cómo"]) && $telegram->text_has(["conseguir", "consigue"]) && $telegram->text_contains(["objeto", "incienso", "cebo", "huevo"])){
    $help = "En principio si vas a las PokeParadas y tienes suerte, también deberías de poder conseguirlos.";
}elseif($telegram->text_contains(["calcular", "calculadora", "página"], ["IV", "porcentaje"])){
    $this->analytics->event('Telegram', 'IV Calculator');
    // $help = "Puedes calcular las IVs de tus Pokemon en esta página: https://pokeassistant.com/main/ivcalculator";
    $help = "Si me dices los datos de tu Pokémon, te puedo calcular yo mismo los IV. :)";
}elseif($telegram->text_contains(["tabla", "lista"]) && $telegram->text_contains(["ataque", "tipos", "tipos de ataque", "debilidad"]) && $telegram->words() < 10){
    $this->analytics->event('Telegram', 'Attack Table');
    $telegram->send
        ->notification(FALSE)
        ->file('photo', FCPATH .'files/attack_types.png');
    exit();
}elseif($telegram->text_contains(["tabla", "lista"]) && $telegram->text_contains(["huevos"]) && $telegram->words() < 10){
    $this->analytics->event('Telegram', 'Egg Table');
    $telegram->send
        ->notification(FALSE)
	// AgADBAADl7MxG10JzgK-Vlne-9fNkaDZZRkABNpfQEaBRT960bsDAAEC
	// FCPATH .'files/egg_list.png'
        ->file('photo', 'AgADBAADl7MxG10JzgK-Vlne-9fNkaDZZRkABNpfQEaBRT960bsDAAEC');
    return -1;
}elseif(
    ( $telegram->text_has(["profe", "oak"]) && $telegram->text_has(["código fuente", "source"]) ) or
    $telegram->text_command("github")
){
    $help = "Puedes inspeccionarme en github.com/duhow/ProfesorOak !\nNo me desnudes mucho que me sonrojo... " .$telegram->emoji("=P");
}elseif($telegram->text_has(["cambiar", "cambio"]) && $telegram->text_has(["facción", "color", "equipo", "team"]) && $telegram->words() <= 12){
    $help = "Según la página oficial de Niantic, aún no es posible cambiarse de equipo. Tendrás que esperar o hacerte una cuenta nueva, pero *procura no jugar con multicuentas, está prohibido.*";
}elseif($telegram->text_has(["cambiar", "cambio"]) && $telegram->text_has(["usuario", "nombre", "apodo", "llamo"]) && $telegram->words() <= 15){
    $help = "Si quieres cambiarte de nombre, puedes hacerlo en los *Ajustes de Pokemon GO.*\nUna vez hecho, habla con @duhow para que pueda cambiarte el nombre aquí!";
}elseif($telegram->text_has("datos") && $telegram->text_has(["móvil", "móviles"]) && !$telegram->text_contains("http")){
    $help = "Si te has quedado sin datos, deberías pensar en cambiarte a otra compañía o conseguir una tarifa mejor. "
            ."Te recomiendo que tengas al menos 4GB si vas a ponerte a jugar en serio.";
}elseif($telegram->text_has(["os funciona", "no funciona", "no me funciona", "problema"]) && $telegram->text_has(["GPS", "ubicación"]) && !$telegram->text_contains(["fake", "bueno", "cerca", "me funciona"])){
    $help = "Si no te funciona el GPS, comprueba los ajustes de GPS. Te recomiendo que lo tengas en modo *sólo GPS*. "
            ."Procura también estar en un espacio abierto, el GPS en casa no funciona a no ser que lo tengas en *modo ahorro*. \n"
            ."Si sigue sin funcionar, prueba a apagar el móvil por completo, espera un par de minutos y vuelve a probar.";
}elseif($telegram->text_has(["recomendar", "recomienda", "comprar", "aconseja"]) && $telegram->text_has("batería", ["externa", "portátil", "recargable", "extra"]) && !$telegram->text_contains(["http", "voy con", "tengo", "del port"])){
    $help = "En función de lo que vayas a jugar a Pokemon GO, puedes coger baterías pequeñas. "
            ."La capacidad se mide en mAh, cuanto más tengas, más tiempo podrás jugar.\n\n"
            ."Si juegas unas 2-3 horas al día, te recomiendo al menos una de 5.000 mAh. Rondan más o menos *8-12€*. "
            ."Pero si quieres jugar más rato, entonces mínimo una de 10.000 o incluso 20.000 mAh. El precio va entre *20-40€*. "
            ."Éstas van bien para compartirlas con la gente, por si tu amigo se queda sin batería (o tu mismo si te llega a pasar).\n"
            ."Recomiendo las que son de marca *Anker* o *RAVPower*, puedes echarle un vistazo a ésta si te interesa: http://www.amazon.es/dp/B019X8EXJI";
}elseif(
    $telegram->text_has(["evolución", "evolucionar", "evoluciones"]) &&
    $telegram->text_contains(["evee", "eevee", "jolteon", "flareon", "vaporeon"]) &&
    $telegram->text_contains(["?", "¿"]) &&
    !$telegram->text_contains(["mejor"])
){
    $help = "Tan sólo hay que *cambiar el nombre de Eevee antes de evolucionarlo* en función del que quieras conseguir.\n\n"
            ."*El truco*\n"
            ."- Si quieres a *Vaporeon* (Agua), llámalo *Rainer*.\n"
            ."- Si quieres a *Jolteon* (Eléctrico), llámalo *Sparky*.\n"
            ."- Si quieres a *Flareon* (Fuego), llámalo *Pyro*.\n\n"
            ."Pero ten en cuenta que este truco *sólo funciona una vez* por cada nombre, así que elige sabiamente...";
            // ."Estos nombres tienen una historia detrás, aunque hay que remontarse a la serie original. "
            // ."En uno de los capítulos, Ash y sus compañeros de viaje se topaban con los hermanos Eeeve, "
            // ."y cada uno de ellos tenía una de las tres evoluciones.\n_¿A que no adivinas como se llamaban los hermanos?_\n";
            // ."https://youtu.be/uZE3CwmCYcY";
}

if(!empty($help)){
    $telegram->send
        ->notification(FALSE)
        ->text($help, TRUE)
    ->send();
    return;
}

// pregunta sobre Eevee
if($telegram->text_has("llama") && $telegram->text_has("eevee")){
    $pkmn = "";
    if($telegram->text_has("agua")){
        $pkmn = "Vaporeon";
    }elseif($telegram->text_has("fuego")){
        $pkmn = "Flareon";
    }elseif($telegram->text_has(["eléctrico", "electricidad"])){
        $pkmn = "Jolteon";
    }
    if(!empty($pkmn)){ $telegram->send->notification(FALSE)->text("Creo que te refieres a *$pkmn*?", TRUE)->send(); }
    return;
}

// Estado servidores Pokemon Go
elseif(
    $telegram->text_has(["funciona", "funcionan", "va", "caído", "caídos", "caer", "muerto", "estado"]) &&
    $telegram->text_has(["juego", "pokémon", "servidor", "servidores", "server", "servers"]) &&
    !$telegram->text_contains(["ese", "a mi me va", "a mis", "Que alg", "esa", "este", "caza", "su bola", "atacar", "cambi", "futuro", "esto", "para", "mapa", "contando", "va lo de", "llevamos", "a la", "va bastante bien"]) &&
    $telegram->words() < 15 && $telegram->words() > 2
){
    /* $web = file_get_contents("http://www.mmoserverstatus.com/pokemon_go");
    $web = substr($web, strpos($web, "Spain"), 45);
    if(strpos($web, "red") !== FALSE){
        $str = "*NO funciona, hay problemas en España.*\n(Si bueno, aparte de los políticos.)";
    }elseif(strpos($web, "green") !== FALSE){
        $str = "Pokemon GO funciona correctamente! :)";
    } */

	$telegram->send->chat_action("typing")->send();

    // Conseguir estado mediante API JSON
    $web = file_get_contents("https://go.jooas.com/status");
    $web = json_decode($web);

    $pkgo = ($web->go_online == TRUE ? ':green-check:' : ':times:');
    $ptc = ($web->ptc_online == TRUE ? ':green-check:' : ':times:');

    $pkgo_t = $web->go_idle;
    $pkgo = ($pkgo == ":green-check:" && $pkgo_t <= 45 ? ':warning:' : ':green-check:');
    $pkgo_t = ($pkgo_t > 120 ? floor($pkgo_t / 60) ."h" : $pkgo_t ."m" );

    $ptc_t = $web->ptc_idle;
    $ptc = ($ptc == ":green-check:" && $ptc_t <= 45 ? ':warning:' : ':green-check:');
    $ptc_t = ($ptc_t > 120 ? floor($ptc_t / 60) ."h" : $ptc_t ."m" );
    // Todo funciona bien
    if($pkgo == TRUE && $ptc == TRUE){ $str = "¡Todo está funcionando correctamente!"; }
    // Problemas con PTC
    elseif($ptc != TRUE){ $str = "El juego funciona, pero parece que el *Club de Entrenadores tiene problemas.*\n_(¿Y cuándo no los tiene?)_"; }
    // Esto no va ni a la de tres
    else{ $str = "Parece que *hay problemas con el juego.*"; }

    $str .= "\n\n$pkgo PKMN ($pkgo_t)\n" ."$ptc PTC ($ptc_t)\n";
    // $str .= "_powered by https://go.jooas.com/ _";
    $str = $telegram->emoji($str);

    $telegram->send
        ->notification(TRUE)
        // ->reply_to(TRUE)
        ->text($str, TRUE)
    ->send();
    return -1;
}

// Significados de palabras
elseif($telegram->text_has("Qué", ["significa", "es"], TRUE)){
    $word = trim(strtolower($telegram->last_word(TRUE)));
    if(is_numeric($word)){ return; }
    $help = $pokemon->meaning($word);

    // Buscar si contiene EL/LA si no ha encontrado el $help, y repetir proceso.

    if(!empty($help) && !is_numeric($help)){
        $this->analytics->event('Telegram', 'Help Meaning', $word);
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text($help, TRUE)
        ->send();
    }
    return -1;
}

// Calcular IV info
elseif(
	($telegram->text_command("iv") or $telegram->text_command("ivs")) &&
	$telegram->words() <= 2
){
	if($pokemon->command_limit("iv", $telegram->chat->id, $telegram->message, 7)){ return -1; }

	$telegram->send
		->notification(FALSE)
		->text("/iv <*Pokémon*> <*CP*> <*HP*> <*Polvos*>", TRUE)
	->send();

	return -1;
}

// Calcular IV TODO
// Ver los IV o demás viendo stats Pokemon.
elseif(
	$telegram->words() >= 4 &&
	($telegram->text_has(["tengo", "me ha salido", "calculame", "calcula iv", "calcular iv", "he conseguido", "he capturado"], TRUE) or
	$telegram->text_command("iv") or $telegram->text_command("ivs"))
){
	// TODO
}

elseif($telegram->text_has(["debilidad", "debilidades", "fortaleza", "fortalezas"], ["contra", "hacia", "sobre", "de"]) && $telegram->words() <= 6){
	// $chat = NULL;
	$text = trim($telegram->text());
	$filter = (strpos($telegram->text(), "/") === FALSE); // Si no hay barra, filtra.
	if(in_array($telegram->words(), [3,4]) && $telegram->text_has("aquí", FALSE)){
		$text = $telegram->words(2, $filter);
		// $chat = ($telegram->is_chat_group() && $this->is_shutup() ? $telegram->user->id : $telegram->chat->id);
	}else{
		$text = $telegram->last_word($filter);
		// $chat = $telegram->user->id;
	}
	$pk = pokemon_parse($telegram->text());
	if(!empty($pk['pokemon'])){ $text = $pk['pokemon']; }
	$this->analytics->event('Telegram', 'Search Pokemon Attack', ucwords(strtolower($text)));
	$target = $telegram->text_contains("fortaleza");
	$str = pokemon_attack($text, $target);

	if(!empty($str)){
		$telegram->send
			->notification(FALSE)
			->text($str, TRUE)
		->send();
	}

	return -1;
}

elseif($telegram->text_has(["aquí hay un", "ahi hay", "hay un"], TRUE) and $telegram->has_reply and $telegram->is_chat_group()){
	// $telegram->send->text("ke dise?")->send();
	if(isset($telegram->reply->location)){
		$loc = $telegram->reply->location['latitude'] ."," .$telegram->reply->location['longitude'];
		$pk = pokemon_parse($telegram->text());
		if(!empty($pk['pokemon'])){
			// $pokemon->settings($telegram->user->id, 'pokemon_select', $pk['pokemon']);

			$pokemon->settings($telegram->user->id, 'location', $loc);
			pokemon_seen($telegram->user->id, $pk['pokemon'], $loc);
			return -1;

			// $pokemon->step($telegram->user->id, 'POKEMON_SEEN');
			// $this->_step();
		}
		if($telegram->text_contains(["cebo", "lure"])){
			$pokemon->settings($telegram->user->id, 'location', $loc);
			$pokemon->step($telegram->user->id, 'LURE_SEEN');
			// $this->_step();
		}
	}
}


?>
