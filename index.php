<!DOCTYPE html>
<html>
<head>
    <title>Test FIDO</title>
    <meta charset=utf-8/>
    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/jquery.splitter-0.14.0.js"></script>
    <link rel="stylesheet" href="css/jquery.splitter.css">
    <link rel="stylesheet" href="css/styles.css">
    <script>
        function panelize() {
            var screen_height = $(window).height();
            $('#container')
                .height(screen_height)
                .split({
                    orientation: 'horizontal',
                    limit: 100,
                    position: '300px'
                });
            $('#topPanel').split({
                orientation: 'vertical',
                limit: 200,
                position: '300px'
            });
        }
        $(document).ready(panelize);
        $(window).resize(panelize);
    </script>
</head>
<body>
<div id="container">
    <div id="topPanel">
        <div id="menu">
            <ul>
                FIDO
                <li><a href="https://github.com/openplanets/fido">Projet GitHub</a></li>
                <li><a href="http://www.openplanetsfoundation.org/software/fido">Page sur openplanetsfoundation</a>
                </li>
            </ul>
            <ul>
                PRONOM
                <li><a href="http://en.wikipedia.org/wiki/PRONOM">Wikipedia</a></li>
                <li><a href="http://www.nationalarchives.gov.uk/PRONOM/Default.aspx">nationalarchives.gov.uk</a>
                </li>
                <li><a href="http://www.nationalarchives.gov.uk/aboutapps/PRONOM/">About</a></li>
                <li>
                    <a href="http://www.nationalarchives.gov.uk/PRONOM/BasicSearch/proBasicSearch.aspx?status=new">Search</a>
                </li>
            </ul>
            <ul>
                DROID
                <li><a href="https://github.com/digital-preservation/droid">Projet GitHub</a></li>
                <li>
                    <a href="http://www.nationalarchives.gov.uk/information-management/manage-information/preserving-digital-records/droid/">Page
                        officielle</a></li>
                <li><a href="http://asalaedev2.asalae.dev.adullact.org/test/droid">Page de test DROID</a></li>
            </ul>
        </div>
        <div id="content">
            <div id="saisie">
                <h1>Page de test : FIDO - Format Identification for Digital Objects</h1>

                <form method="post" action="#" enctype="multipart/form-data">
                    <fieldset>
                        <legend>1 - Selectionner du fichier</legend>
                        <input type="file" name="file"/>
                    </fieldset>
                    <fieldset id="args">
                        <legend>2 - Selection des options</legend>
                        <label for="verbose">Mode verbeux (Debug)</label>
                        <input type="checkbox" name="verbose" id="verbose" checked/>
                        <hr>
                        <label for="csv">Export CSV</label>
                        <input type="checkbox" name="csv" id="csv"/>
                        <label for="zip">Analyser contenu (formats archives)</label>
                        <input type="checkbox" name="zip" id="zip"/>
                    </fieldset>
                    <fieldset>
                        <legend>3 - Lancer l'analyse</legend>
                        <input type="submit" value="Tester"/>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
    <div id="bottom_panel">
        <div id="debug">
            <strong>Console</strong>
            <hr/>
			<pre id="content-debug">
<?php
if (isset($_FILES['file'])) {
    if ($_FILES['file']['error']) {
        switch ($_FILES['file']['error']) {
            case 1: // UPLOAD_ERR_INI_SIZE
                echo "Le fichier dépasse la limite autorisée par le serveur (fichier php.ini) !";
                break;
            case 2: // UPLOAD_ERR_FORM_SIZE
                echo "Le fichier dépasse la limite autorisée dans le formulaire HTML !";
                break;
            case 3: // UPLOAD_ERR_PARTIAL
                echo "L'envoi du fichier a été interrompu pendant le transfert !";
                break;
            case 4: // UPLOAD_ERR_NO_FILE
                echo "Le fichier que vous avez envoyé a une taille nulle !";
                break;
        }
    } else { //pas d'erreur d'envoi
        if (isset($_FILES['file']['tmp_name'])) {
            // relever le point de départ
            $timestart = microtime(true);
            // chargement de la librairie
            require_once 'lib/Fido.php';
            if (isset($_POST['verbose'])) {
                // affichage des infos du navigateur
                $infos = "\nInformations navigateur :\n<p class='important'>";
                $infos .= "NOM: " . $_FILES['file']['name']; //Le nom original du fichier, comme sur le disque du visiteur (exemple : mon_icone.png).
                $infos .= "<br/>TYPE MIME: " . $_FILES['file']['type'];  //Le type du fichier. Par exemple, cela peut être « image/png ».
                $infos .= "<br/>TAILLE: " . $_FILES['file']['size'];   //La taille du fichier en octets.
                $infos .= "<br/>TMP_NAME: " . $_FILES['file']['tmp_name']; //L'adresse vers le fichier uploadé dans le répertoire temporaire.
                $infos .= "<br/>CODE ERREUR: " . $_FILES['file']['error']; //Le code d'erreur, qui permet de savoir si le fichier a bien été uploadé.
                echo $infos . "</p>";
            }

            if (isset($_POST['verbose'])) {
                echo "Options : <p class='important'>";
                print_r($_POST);
                echo "</p>";
            }

            echo "Lancement de la commande FIDO \n";
            // préparation des arguments
            $args = array();
            if (isset($_POST['quiet']))
                $args['-q'] = '';

            if ($_FILES['file']['type'] == "application/zip" && isset($_POST['zip']))
                $args['-zip'] = '';

            $csv = isset($_POST['csv']) ? 'files/test.csv' : '';

            // éxecution de l'analyse
            $output = Fido::analyze($_FILES['file']['tmp_name'], $args, $csv);

            foreach ($output as $file) {
                echo "<p class='important'>";
                print_r($file);
                if ($file['result'] == "OK")
                    echo "<br><a href='http://www.nationalarchives.gov.uk/pronom/" . $file['puid'] . "'>+ d'infos sur ce type de fichier sur le repertoire PRONOM</a>";
                echo "</p>";
            }

            if (isset($_POST['verbose'])) {
                // temps d'éxecution
                $timeend = microtime(true);
                $time = $timeend - $timestart;
                $page_load_time = number_format($time, 3);
                echo "Debut du script: " . date("H:i:s", $timestart);
                echo "<br>Fin du script: " . date("H:i:s", $timeend);
                echo "<br>Script execute en " . $page_load_time . " sec";
            }
        }
    }
} else { //Fichier non envoyé
    passthru('python -m fido.fido -h');
}
?>
			</pre>
        </div>
    </div>
</div>
</body>
</html>
