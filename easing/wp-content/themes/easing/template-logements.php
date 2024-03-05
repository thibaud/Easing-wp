<?php /* Template Name: Liste logements */

require_once 'header.php';

require_once 'Code_API/UtilsAPI.php';

echo "<main>";

//echo "<pre>".print_r($_POST, true)."</pre>";

$destination=$_POST["destination"];
$debut=$_POST["debut"];
$fin=$_POST["fin"];
$nombre_personne=$_POST["champNombre"];
$user_request = $_POST["user_request"];

$taxonomy = 'accessibilite';

// Récupérer tous les termes de la taxonomie (excluant les termes sans post associé)
$terms = get_terms(array(
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'pad_counts' => true, // Exclure les termes sans post associé
));
$filtered_terms = wp_list_filter($terms, array('count' => 0), 'NOT');

$is_AI_recomandation = !empty($user_request);
if (!$is_AI_recomandation){
    error_log("=====================================================================================================================");
    error_log("                                                 Filter Without IA");
    error_log("=====================================================================================================================");
    error_log("Filter Without IA");

    $args = array(
        'post_type'      => 'logement',
        'posts_per_page' => -1,
    );

    if (!empty($nombre)) {
        $args['meta_query'][] = array(
            'key'     => 'nombre_personnes',
            'value'   => $nombre,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }

    if (!empty($destination)) {
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => 'ville',
                'value'   => $destination,
                'compare' => 'LIKE',
            ),
            array(
                'key'     => 'region',
                'value'   => $destination,
                'compare' => '=',
            ),
        );
    }

    if (!empty($nombre) && !empty($destination)) {
        $args['meta_query'] = array(
            'relation' => 'AND',
            $args['meta_query'],
        );
    }

    error_log("Get post filters");
    $logements = get_posts($args);
    $list_logements = array();

    foreach ($logements as $logement) {
        $fields_logement = get_fields($logement->ID);
        $fields_logement['link'] = get_permalink($logement->ID); // Ajoute le lien du logement au tableau
        $list_logements[] = $fields_logement;
    }

} else {
    error_log("=====================================================================================================================");
    error_log("                                                 Filter With IA");
    error_log("=====================================================================================================================");

    $token = get_API_Token();
    $token_access = $token["access_token"];

    $URL = "http://localhost:8000/IA/search";

    $body = array(
        "destination"=>$destination,
        "start"=>$debut,
        "end"=>$fin,
        "number_person"=>intval($nombre_personne),
        "n_result"=>30,
        "method"=>"TF-IDF",
        "similarity_method"=>"cosine",
        "user_request"=>$user_request,
    );

    $jsonData = json_encode($body);

    // Set the HTTP headers
    $options = array(
        'http' => array(
            'header'  => "Content-Type: application/json\r\n" . "Accept: application/json\r\n" . "Authorization: bearer $token_access\r\n",
            'method'  => 'POST',
            'content' => $jsonData
        )
    );

    // Create a stream context
    $context  = stream_context_create($options);

    $response = file_get_contents($URL, false, $context);

    // Check for errors
    if ($response === false) {
        // Handle cURL error
        // Handle error
        echo "Error: ";
    } else {
        // Response received successfully, handle it

        $decoded_response = json_decode($response, true);


        error_log("==========================================================================================");
        error_log("==================================== Results =============================================");
        error_log("==========================================================================================");

        error_log("Number of result: ". $decoded_response['number_of_results']);

        $results = $decoded_response['result'];

        // Get nodes properties
        $logements = array();
        foreach ($results as $node){
            $node['nodes']['ID'] = $node['nodes']['ID_Post'];
            $logements[] = $node['nodes'];
        }

//        error_log("Nodes: ".print_r($logements, true));

        foreach ($logements as $logement) {
            $fields_logement = get_fields($logement['ID_Post']);
            $fields_logement['link'] = get_permalink($logement['ID_Post']); // Ajoute le lien du logement au tableau
            $list_logements[] = $fields_logement;
        }
    }
}

error_log(print_r($logements, true));

$json_logements = json_encode($list_logements);

?>

<form  style="padding-top:20px;padding-bottom:30px;box-shadow: 3px 2px 8px 1px rgba(0, 0, 0, 0.3);" class="search_form accueil_form d-flex align-items-end justify-content-center flex-row" action="<?php echo esc_url(get_permalink(27)); ?>" method="post" style="margin-bottom:50px">

        <!-- Champ nombre -->
        <div class="d-flex flex-column champ">
            <label class="bold" for="destination">Destination</label>
            <input type="text" id="destination" name="destination" value="<?php echo $destination ?>">
        </div>

        <div class="d-flex flex-column champ">
            <label class="bold" for="debut">Date de début</label>
            <input type="date" id="debut" name="debut" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $debut ?>" />
        </div>

        <div class="d-flex flex-column champ">
            <label class="bold" for="fin">Date de fin </label>
            <input type="date" id="fin" name="fin" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo $fin ?>" ?> 
        </div>
        <!-- Champ nombre -->
        <div class="d-flex flex-column champ">
            <label class="bold" for="champNombre">Voyageurs</label>
            <input type="number" id="champNombre" name="champNombre" value="<?php echo $nombre_personne ?>">
            
        </div>

        <div class="d-flex flex-column champ champ_filtre" style="margin-right:25px">
            <label style="margin-bottom:0" class="bold" for="">Filtres</label>
            <img style="width:33px" src="<?php echo get_bloginfo('template_url'); ?>/img/filter.svg" alt="" >

        </div>

        <!-- Bouton de soumission -->
        <button class="envoyer d-flex justify-content-center align-items-center" type="submit" value=""> 
            <img src="<?php echo get_bloginfo('template_url'); ?>/img/search-white.svg" alt="" >
        </button>

        <div class="filtre-popup align-items-center justify-content-center"> 

            <div class="d-flex flex-column pop-pup-content" >
                
                <p class="croix_filtre" > + </p>

                <h3 style="margin-bottom:25px"> Filtres </h3>
                
                <div style="margin-bottom:10px;flex-flow:row wrap" class="d-flex tags-utilise">
                    
                </div>

                <div  style="flex-flow:row wrap" class="d-flex tags">

                    <?php foreach ($filtered_terms as $term) { ?>

                        <div style="margin-bottom:10px" class="tag tag-noselect"> <?php echo $term->name; ?> </div>
                            
                    <?php  } ?>

                </div>

                <div>
                    <label id="user_request_label" for="user_request" name="user_request">AI Request</label>
                    <input type="text" id="user_request" name="user_request" value="<?php echo $user_request; ?>">
                </div>

               
            </div>

        </div> 

</form>



<?php $startDate = new DateTime($debut);
$endDate = new DateTime($fin);
// Calculez la différence en jours
$difference = $startDate->diff($endDate);
$nombre_nuit=$difference->days; ?>

<div class="d-flex flex-row" style="min-height: calc(100vh - 60px - 40px - 75px);max-height: calc(100vh - 60px - 40px - 75px)"> 

    <div class="liste-logements d-flex"> 
        <?php  $i=0; foreach ($logements as $logement) {
            $logement_id = $is_AI_recomandation ? $logement['ID'] : $logement->ID;
            $fields_logement=get_fields($logement_id);
            if(!empty($debut)){
                $lien = get_permalink($logement_id) . "?nombre=$nombre&destination=$destination&debut=$debut&fin=$fin";}
                
                else{
                    // Date actuelle
                    $dateDebut = new DateTime();

                    // Ajout de 2 mois à la date actuelle
                    $dateFin = clone $dateDebut;
                    $dateFin->modify('+2 months');

                    // Conversion en timestamps
                    $dateDebutTimestamp = $dateDebut->getTimestamp();
                    $dateFinTimestamp = $dateFin->getTimestamp();

                    // Génération de la première date au hasard dans la plage spécifiée
                    $dateAleatoire1 = rand($dateDebutTimestamp, $dateFinTimestamp);

                    // Ajout d'une semaine à la première date pour la deuxième date (en secondes)
                    $dateAleatoire2 = $dateAleatoire1 + 60 * 60 * 24 * 7;

                    // Vérifier si les deux dates sont dans le même mois
                    while (date('n', $dateAleatoire1) != date('n', $dateAleatoire2)) {
                        $dateAleatoire1 = rand($dateDebutTimestamp, $dateFinTimestamp);
                        $dateAleatoire2 = $dateAleatoire1 + 60 * 60 * 24 * 7;
                    }

                    // Formatage en utilisant strftime pour les mois en français et affichage en une seule ligne
                    setlocale(LC_TIME, 'fr_FR');
                    $lien = get_permalink($logement->ID) . "?nombre=$nombre&destination=$destination&debut=" . date('Y-m-d', $dateAleatoire1) . "&fin=" . date('Y-m-d', $dateAleatoire2);
                }
            ?>

            <a id="<?php echo $i ?>" class="card_logement"   style="width:32%" href="<?php echo $lien ?>" target="_blank">

                <div class="owl-carousel carousel-logement owl-theme">
                    <?php foreach($fields_logement['photos'] as $photo){ ?>
                        <img  src="<?php echo $photo['url']  ?>" >
                    <?php } ?>
                </div> 

                <div class="informations" >
                <p class="ville"><?php echo $fields_logement['ville'] ?>, France </p>
                <p><?php echo $fields_logement['titre'] ?> </p>
                <p><?php echo $fields_logement['statut'] ?> </p>
                <?php if(empty($debut)){ ?> 
                    <p><?php echo strftime("%e – ", $dateAleatoire1) . strftime("%e %b", $dateAleatoire2); ?></p>
                <?php } ?>
                <p class="prix"><span class="bold"> <?php echo $fields_logement['prix_nuit'] ?> </span> € par nuit </p>
                <?php if($nombre_nuit!=0){ ?>
                    <p><span class="bold"><?php echo $fields_logement['prix_nuit']*$nombre_nuit ?></span>€ au total </p>
                <?php }else{ ?>
                    <p><span class="bold"><?php echo $fields_logement['prix_nuit']*7 ?></span>€ au total </p>
                <?php } ?>
                <div class="coeur" > </div>
                </div>

            </a>
            
        <?php $i++;} ?>
    </div> 

    <div style="height:auto;width:40%" id="map"></div>

    <!-- Utiliser un attribut data pour stocker la chaîne JSON -->
    <div id="logements" data-logements='<?php echo htmlspecialchars($json_logements, ENT_QUOTES, 'UTF-8'); ?>'></div>

 </div>

</main>

<?php
include 'footer.php';
?>