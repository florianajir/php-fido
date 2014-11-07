<?php

/**
 * Created on 3 sept. 2013
 * @author Florian Ajir <florianajir@gmail.com>
 *
 * Librairie PHP permettant l'identification de document électronique
 * en faisant appel au programme FIDO
 * @link <http://www.openplanetsfoundation.org/software/fido>
 * basé sur le registre technique PRONOM
 * @link <http://www.nationalarchives.gov.uk/PRONOM/Default.aspx>
 *
 * Plus précis que le mime/type, le répertoire PRONOM a été créé à l'initiative des archives nationales de Grande Bretagne
 * Et permet d'identifier précisément le format des fichiers
 *
 * Usage du script fido:
 *
 *        fido.py [-h] [-v] [-q] [-recurse] [-zip] [-nocontainer] [-input INPUT]
 *             [-filename FILENAME] [-useformats INCLUDEPUIDS]
 *             [-nouseformats EXCLUDEPUIDS] [-matchprintf FORMATSTRING]
 *             [-nomatchprintf FORMATSTRING] [-bufsize BUFSIZE]
 *             [-container_bufsize CONTAINER_BUFSIZE]
 *             [-loadformats XML1,...,XMLn] [-confdir CONFDIR]
 *             [FILE [FILE ...]]
 */
class Fido {

    /**
     * Chemin de l'éxecutable python si usage d'une version compilée
     * par défaut : python
     */
    const python = 'python';
    /**
     * Commande de lancement de fido
     * si fido a été installé en tant que module python (python setup.py install) : -m fido.fido
     * sinon chemin vers le script fido.py
     */
    const fido = '-m fido.fido';
    /**
     * Constante pour le format de sortie des résultats dont l'analyse a réussi
     */
    const matchPrintf = '-matchprintf "OK,%(info.filename)s,%(info.puid)s,%(info.formatname)s,%(info.version)s,%(info.signaturename)s,%(info.mimetype)s\n" ';
    /**
     * Constante pour le format de sortie des résultats dont l'analyse a échoué
     */
    const nomatchPrintf = '-nomatchprintf "KO,%(info.filename)s\n" ';

    /**
     * Directory Separator
     */
    const DS = '/';

    /**
     * Analyse un fichier, une archive ou un dossier
     * avec des arguments passés en paramètre
     * @param string $target Adresse du fichier/dossier
     * @param array $args Arguments à passer en paramètre à FIDO
     * @param string $redirectionFile Fichier vers lequel rediriger les résultats
     * @return array réponse parsée @see Fido::_parseResponse
     *
     * @see Fido::_execute
     */
    public static function analyze($target, $args = array(), $redirectionFile = null) {
        return self::_execute($target, $args, $redirectionFile);
    }

    /**
     * Analyse un fichier
     * @param string $target Adresse du fichier
     * @return array réponse parsée @see Fido::_parseResponse
     *
     * @see Fido::_execute
     */
    public static function analyzeFile($target) {
        $return = self::_execute($target);
        return $return[0];
    }

    /**
     * Analyse une archive (ZIP ou TAR) (32 bit zipfiles only)
     * @param string $archive adresse de l'archive
     * @return array réponse parsée @see Fido::_parseResponse
     *
     * @see Fido::_execute
     */
    public static function analyzeArchive($archive) {
        $args = array("-zip" => '');
        return self::_execute($archive, $args);
    }

    /**
     * Analyse un dossier
     * @param string $folder Adresse du dossier
     * @param boolean $recursive Analyse récursive ou non
     * @param boolean $archives Analyse le contenu des archives ou non
     * @return array réponse parsée @see Fido::_parseResponse
     *
     * @see Fido::_execute
     */
    public static function analyzeFolder($folder, $recursive = false, $archives = false) {
        $args = array();
        if ($recursive)
            $args['-recurse'] = "";
        if ($archives)
            $args['-zip'] = "";
        return self::_execute($folder, $args);
    }

    /**
     * Fonction interne qui execute l'analyse, appelée par les autres fonctions
     * @param string $target Cible de l'analyse
     * @param array $args_list Liste des arguments à utiliser avec fido
     * @param string $redirectionFile Adresse du fichier vers lequel rediriger
     * @return array réponse parsée @see Fido::_parseResponse
     */
    protected static function _execute($target, $args_list = array(), $redirectionFile = null) {
        $output = array();
        $redirect = '';
        /**
         * transformation de la liste d'arguments en chaine
         * $args['-option'] = valeur devient "-option valeur"
         */
        $args = " ";
        foreach ($args_list as $option => $value)
            $args .= $option . " " . $value . " ";

        if (!empty($redirectionFile)) {
            // remplacement des espaces dans le chemin des fichiers
            $redirectionFile = str_replace(' ', '_', $redirectionFile);
            $redirect = " > " . escapeshellarg($redirectionFile);
        }

        $target = escapeshellarg($target);

        // construction de la chaine de commande et supprimer les espaces multiples
        $command = preg_replace('!\s+!', ' ',
            self::python . ' ' . self::fido . ' ' . $args . self::matchPrintf . self::nomatchPrintf . $target . $redirect);

        exec($command, $output);
        return self::_parseResponse($output, $redirectionFile);
    }

    /**
     * Fonction de parsage de la réponse de fido à Fido::_execute
     * @param array $responses Réponse de Fido
     * @param string $redirection fichier de sortie
     * @return array Réponse parsée, example :
     * [0] => Array
     *  (
     *      [result] => OK
     *        [filename] => "chemin_du_fichier"
     *      [puid] => fmt/291
     *      [formatname] => "OpenDocument Text"
     *      [version] => "1.2"
     *      [signaturename] => "ODF 1.2 text"
     *      [mimetype] => "application/vnd.oasis.opendocument.text"
     *  ),
     * [1] => Array
     *  (
     *      [result] => KO
     *        [filename] => "chemin_du_fichier"
     *  )
     */
    protected static function _parseResponse($responses, $redirection = null) {
        $parsed = array();
        $lines = empty($redirection) ? $responses : file($redirection);
        foreach ($lines as $line) {
            $data = explode(',', $line);
            if ($data[0] == "OK") {
                $parsed[] = array(
                    'result' => $data[0],
                    'filename' => $data[1],
                    'puid' => $data[2],
                    'formatname' => $data[3] == 'None' ? null : $data[3],
                    'version' => $data[4] == 'None' ? null : $data[4],
                    'signaturename' => $data[5] == 'None' ? null : $data[5],
                    'mimetype' => $data[6] == 'None' ? null : $data[6],
                );
                break;
            } else {
                $parsed[] = array(
                    'result' => $data[0],
                    'filename' => $data[1],
                );
            }
        }
        return $parsed;
    }

    /**
     * retourne la version de fido (fido -v)
     * @return string version de fido
     */
    public static function version() {
        // initialisations
        $output = array();

        // préparation de la ligne de commande
        $command = preg_replace('!\s+!', ' ', self::python . ' ' . self::fido . ' -v');

        // exécution
        exec($command, $output);

        // parsing
        if (count($output) == 0) return '';
        $versionLine = $output[0];
        $versionLineElements = explode(' ', $versionLine);
        if (count($versionLineElements) == 0) return '';
        return $versionLineElements[1];
    }

    /**
     * retourne l'uri du fichier versions.xml
     * @return string uri du fichier versions.xml
     */
    public static function xmlVersionsFileUri() {
        // recherche dans le dossier /usr/local/lib/python*/dist-packages/fido*/fido/conf/versions.xml
        $versionsFileDir = glob('/usr/local/lib/python*/dist-packages/fido*/fido/conf/versions.xml');
        if (count($versionsFileDir) > 0)
            return array_pop($versionsFileDir);

        // recherche dans le dossier /opt/fido*/fido/conf/versions.xml
        $versionsFileDir = glob('/opt/fido*/fido/conf/versions.xml');
        if (count($versionsFileDir) > 0)
            return array_pop($versionsFileDir);

        return '';
    }

    /**
     * retourne la version pronom
     * @return string version du répertoire pronom
     */
    public static function pronomVersion() {
        // initialisations
        $xmlVersionsFileUri = Fido::xmlVersionsFileUri();
        if (empty($xmlVersionsFileUri)) return '';

        // chargement du fichier
        $domDoc = new DomDocument();
        $domDoc->load($xmlVersionsFileUri);

        // pronomVersion
        return $domDoc->getElementsByTagName('pronomVersion')->item(0)->nodeValue;
    }

    /**
     * retourne le nom du fichier de signature
     * @return string nom du fichier de signature
     */
    public static function pronomSignature() {
        // initialisations
        $xmlVersionsFileUri = Fido::xmlVersionsFileUri();
        if (empty($xmlVersionsFileUri)) return '';

        // chargement du fichier
        $domDoc = new DomDocument();
        $domDoc->load($xmlVersionsFileUri);

        // pronomVersion
        return $domDoc->getElementsByTagName('pronomSignature')->item(0)->nodeValue;
    }

    /**
     * retourne l'uri du fichier de signature
     * @return string nom du fichier de signature
     */
    public static function pronomSignatureXmlFileUri() {
        // initialisations
        $xmlVersionsFileUri = Fido::xmlVersionsFileUri();
        if (empty($xmlVersionsFileUri)) return '';

        // chargement du fichier
        $domDoc = new DomDocument();
        $domDoc->load($xmlVersionsFileUri);

        // pronomVersion
        $pronomSignatureFilename = $domDoc->getElementsByTagName('pronomSignature')->item(0)->nodeValue;

        return dirname($xmlVersionsFileUri) . self::DS . $pronomSignatureFilename;
    }

    /**
     * retourne la liste des puids utilisés par Fido formaté comme suit :
     * array('puid'=>'puid : nom (Version=) (*.ext, *.ext, ...)')
     * @param array $inList liste des puid a retourner (si vide, retourne tous les puid)
     * @return array liste des puids
     */
    public static function puidList($inList = array()) {
        // initialisations
        /**
         * @var DOMNodeList $formatNodes
         * @var DOMNodeList $formatNode
         * @var DOMNodeList $versionNodes
         * @var DOMNodeList $extensionNodes
         */
        $ret = array();
        $pronomSignatureXmlFileUri = Fido::pronomSignatureXmlFileUri();
        if (empty($pronomSignatureXmlFileUri)) return array();

        // chargement du fichier
        $domDoc = new DomDocument();
        $domDoc->load($pronomSignatureXmlFileUri);

        // parcours du fichier
        $formatNodes = $domDoc->getElementsByTagName('format');
        foreach ($formatNodes as $formatNode) {
            // puid
            $puid = $formatNode->getElementsByTagName('puid')->item(0)->nodeValue;
            if (!empty($inList) && !in_array($puid, $inList)) continue;
            // name
            $name = $formatNode->getElementsByTagName('name')->item(0)->nodeValue;
            // version
            $version = '';
            $versionNodes = $formatNode->getElementsByTagName('version');
            if ($versionNodes->length > 0)
                $version = $versionNodes->item(0)->nodeValue;
            // extension
            $extensionNodes = $formatNode->getElementsByTagName('extension');
            $extension = '';
            if ($extensionNodes->length == 1)
                $extension = '*.' . $extensionNodes->item(0)->nodeValue;
            elseif ($extensionNodes->length > 1)
                foreach ($extensionNodes as $extensionNode) $extension .= (empty($extension) ? '' : ', ') . '*.' . $extensionNode->nodeValue;
            $ret[$puid] = $puid . ' : ' . $name . (strlen($version) == 0 ? '' : ' (Version=' . $version . ')') . (empty($extension) ? '' : ' (' . $extension . ')');
        }
        return $ret;
    }

}
