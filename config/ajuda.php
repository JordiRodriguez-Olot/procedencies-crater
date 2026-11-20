<?php

/*
 * Textos d'ajuda contextual de cada pàgina (bloc «Com funciona?» sota el títol).
 * Es poden editar lliurement: **text** surt en negreta.
 *  - resum:    una frase, sempre visible.
 *  - passos:   què fer, en ordre.
 *  - consells: matisos i errors habituals.
 */
return [

    'index' => [
        'resum'    => 'Quants visitants han vingut i d\'on, segons els filtres que triïs.',
        'passos'   => [
            'Tria **producte**, **any** i/o **mes** al filtre i prem **Filtrar**. Sense filtres veus tot l\'històric.',
            'Les xifres de dalt (visitants, països, comarques, % internacional) i tots els gràfics es recalculen amb el filtre.',
            'A **Comparativa entre períodes** pots comparar dos mesos o dos anys sencers.',
            'Amb **Exportar** descarregues el que estàs veient en Excel o PDF.',
        ],
        'consells' => [
            'Per triar diversos productes alhora, mantén premuda la tecla **Ctrl** mentre cliques.',
            'Si una xifra no et quadra, mira primer **Importacions** (pot faltar un mes) i **Gestió d\'errors** (CP sense província o comarca no surten als gràfics territorials).',
        ],
    ],

    'mapes' => [
        'resum'    => 'Els mateixos visitants que a Procedències, però sobre el mapa: comarques, províncies i països.',
        'passos'   => [
            'Aplica els filtres de producte, any i mes si vols un període concret.',
            'Canvia de mapa amb les pestanyes **Catalunya**, **Espanya** o **Món**.',
            'Passa el ratolí per sobre d\'una zona per veure\'n el nombre de visitants.',
        ],
        'consells' => [
            'Les zones en gris no tenen cap visitant en el període triat.',
            'Els visitants amb codi postal incorrecte no surten al mapa de comarques fins que no es corregeixen a **Gestió d\'errors**.',
        ],
    ],

    'informes' => [
        'resum'    => 'Registre de tot el que s\'ha canviat a l\'aplicació: qui, què i quan.',
        'passos'   => [
            'Filtra per tipus d\'acció si busques alguna cosa concreta (importacions, correccions, usuaris...).',
            'Cada fila indica la data, la persona i el detall del canvi.',
        ],
        'consells' => [
            'És la primera pàgina a mirar quan una dada ha canviat i no saps per què.',
            'No es pot modificar ni esborrar: és l\'historial oficial de l\'aplicació.',
        ],
    ],

    'importacions' => [
        'resum'    => 'Comprova que cada mes tingui les dades dels tres canals, i consulta o desfés les importacions fetes.',
        'passos'   => [
            'Al **Control de càrrega mensual**, cada casella és un mes d\'un canal. Hauria d\'estar en verd (✓).',
            'Si una casella surt en vermell (✕), falta importar aquell mes. Si surt ⧉, s\'ha pujat el mateix fitxer dues vegades.',
            'Passa el ratolí per sobre d\'una casella per veure\'n el motiu.',
            'A l\'historial de sota, el botó **Desfer** esborra totes les entrades d\'una importació concreta.',
        ],
        'consells' => [
            'Una **desviació gran** (↕) no és necessàriament un error: pot ser un mes amb més o menys visites de l\'habitual. Només cal comprovar que el fitxer era el correcte.',
            'Si has importat un fitxer equivocat, el més net és **Desfer** aquella importació i tornar-la a fer amb el fitxer bo.',
        ],
    ],

    'import' => [
        'resum'    => 'Puja l\'Excel mensual de Clorian d\'un canal. Abans de desar res, l\'aplicació el revisa i et mostra un informe.',
        'passos'   => [
            'Exporta de Clorian l\'informe de procedències del mes (un fitxer per canal: Taquilla, Online o CGR).',
            'Tria el **canal** correcte i el fitxer, i prem **Revisar el fitxer**. Encara no es desa res.',
            'Llegeix l\'informe: el mes detectat, la comparació amb mesos anteriors i els avisos.',
            'Si hi ha **correccions proposades**, marca les que vulguis aplicar. Les que no marquis s\'importen tal com venen.',
            'Prem **Importar** (o **Substituir** si aquell mes ja tenia dades). Si alguna cosa no et quadra, **Cancel·lar** no desa res.',
        ],
        'consells' => [
            'Els avisos en **vermell** solen voler dir que el fitxer o el canal no són els correctes: revisa-ho abans de continuar.',
            'Els avisos en **taronja** són dades a revisar, però no impedeixen importar: després apareixen a **Gestió d\'errors**.',
            'Si et diu que **ja hi ha dades d\'aquest mes**: «Substituir» les canvia per les noves; «Afegir» les suma (només si el fitxer conté reserves diferents de les ja carregades).',
        ],
    ],

    'import_general' => [
        'resum'    => 'Càrrega d\'un Excel consolidat amb molts mesos i canals alhora. Per a l\'ús mensual, fes servir la importació normal.',
        'passos'   => [
            'Puja l\'Excel consolidat (full tipus «Dades completades»).',
            'Revisa el resum per mes i canal, i quins mesos ja tenien dades.',
            'Tria què fer amb els mesos que ja existeixen: el més segur és importar només els nous.',
        ],
        'consells' => [
            'Aquesta opció és per a càrregues històriques puntuals. No porta noms d\'entitats de CGR, així que la detecció de duplicats escolars hi funciona pitjor.',
        ],
    ],

    'errors' => [
        'resum'    => 'Dades importades que no s\'han pogut situar bé al mapa (país, codi postal, província o comarca) i possibles grups comptats dues vegades.',
        'passos'   => [
            'Cada targeta de dalt és un tipus d\'error amb el nombre de casos pendents. Fes-hi clic per veure\'n el detall.',
            'Quan l\'aplicació pot deduir el valor correcte, el mostra amb 💡 i n\'hi ha prou amb el botó verd **Aplicar ✓**.',
            'Si no hi ha suggeriment, escriu o tria el valor correcte a mà.',
            'Cada correcció s\'aplica a totes les entrades amb el mateix codi postal i país, i queda al registre d\'activitat.',
        ],
        'consells' => [
            'Les correccions de comarca i província es recorden: el mes que ve, el mateix CP ja s\'importarà corregit.',
            'Si hi ha molts casos alhora (per exemple després d\'una càrrega gran), comença per **Revisió massiva**.',
            'Si un cas és correcte tal com està (per exemple, un visitant que realment no va dir d\'on venia), descarta\'l a **Revisió massiva** perquè no torni a sortir.',
            '**Re-derivar tot** torna a calcular província i comarca de totes les entrades a partir del CP. Només cal si s\'han actualitzat les taules de codis postals.',
        ],
    ],

    'revisio_massiva' => [
        'resum'    => 'Resol de cop molts errors del mateix tipus, quan hi ha massa casos per anar un a un.',
        'passos'   => [
            'Cada bloc és un tipus d\'error amb una acció que n\'arregla molts alhora (per exemple, aplicar tots els suggeriments de comarca).',
            'Abans d\'aplicar-la, pots obrir **Veure casos pendents** per comprovar què canviarà.',
            'El que quedi pendent, revisa-ho un a un a **Gestió d\'errors**, o **Descarta-ho** aquí si ja està bé.',
        ],
        'consells' => [
            'Els casos descartats no tornen a sortir com a error, ni a Gestió d\'errors ni quan s\'importa un fitxer nou.',
            'Totes les accions queden al registre d\'activitat amb el nombre de casos afectats.',
        ],
    ],

    'productes' => [
        'resum'    => 'Agrupa els noms de producte de Clorian perquè es puguin filtrar junts (per exemple, totes les variants de «Visita guiada»).',
        'passos'   => [
            'A la vista per productes, assigna un **grup** a cada producte. Els que no en tenen surten destacats.',
            'A la vista per grups pots moure un producte d\'un grup a un altre.',
            'Si saps que vindrà un producte nou, pots deixar-ne el grup preparat a **Mapatges previstos**.',
        ],
        'consells' => [
            'Si has creat dos grups pel mateix concepte, fusiona\'ls amb **Fusionar dos grups**.',
            'Canviar un grup no toca les dades: només canvia com s\'agrupen als filtres i gràfics.',
        ],
    ],

    'neteja_dades' => [
        'resum'    => 'Esborra dades importades per error (per exemple un mes carregat dues vegades). Fes-la servir amb compte.',
        'passos'   => [
            'Tria els criteris (any, mes, canal, producte) i prem **Calcular abast** per veure quantes files i pax s\'esborrarien.',
            'Si és el que esperaves, escriu **ELIMINAR** per confirmar.',
            'Cada neteja queda a l\'historial de sota, amb qui l\'ha feta.',
        ],
        'consells' => [
            'Per desfer una sola importació, és més senzill el botó **Desfer** d\'**Importacions**.',
            'Les entrades manuals es protegeixen i no s\'esborren, si no ho marques expressament.',
            'La **zona de perill** ho esborra tot: només per a incidències greus i amb una còpia de seguretat feta.',
        ],
    ],

    'usuaris' => [
        'resum'    => 'Qui pot entrar a l\'aplicació i amb quin rol.',
        'passos'   => [
            'Crea un usuari **local** amb nom i contrasenya, o bé, si l\'Active Directory està activat a **Configuració**, dona d\'alta el personal de l\'Ajuntament amb **Cerca a l\'Active Directory** (entren amb l\'usuari i contrasenya de Windows).',
            'Canvia el rol des del desplegable de cada fila: **Admin** pot gestionar usuaris i configuració; **Usuari** fa la resta.',
        ],
        'consells' => [
            'Si algú no ha d\'entrar més però vols conservar-ne el rastre, **Desactiva\'l** en lloc d\'eliminar-lo.',
            'No hi ha recuperació de contrasenya per correu: si un usuari local l\'oblida, reinicia-la des de la taula.',
        ],
    ],
];
