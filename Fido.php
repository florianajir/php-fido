<?php

/**
 * @author Florian Ajir <florian.ajir@adullact.org>
 * @editor Adullact
 *
 * @created 3 sept. 2013
 *
 * Librairie PHP permettant l'identification de document électronique 
 * en faisant appel au programme FIDO 
 * 	@link Fido <http://www.openplanetsfoundation.org/software/fido>
 * basé sur le registre technique PRONOM 
 * 	@link Pronom <http://www.nationalarchives.gov.uk/PRONOM/Default.aspx>
 * 
 * Plus précis que le mime/type, le répertoire PRONOM a été créé à l'initiative des archives nationales de Grande Bretagne
 * Et permet d'identifier précisément le format des fichiers
 * 
 * Usage du script fido: 
 * 
 * 		fido.py [-h] [-v] [-q] [-recurse] [-zip] [-nocontainer] [-input INPUT]
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
	const fido	= '-m fido.fido';
	/**
	 * Constante pour le format de sortie des résultats dont l'analyse a réussi
	 */
	const matchPrintf = '-matchprintf "OK,%(info.filename)s,%(info.puid)s,%(info.formatname)s,%(info.version)s,%(info.signaturename)s,%(info.mimetype)s\n" ';
	/**
	 * Constante pour le format de sortie des résultats dont l'analyse a échoué
	 */
	const nomatchPrintf = '-nomatchprintf "KO,%(info.filename)s\n" ';

	/**
	 * Analyse un fichier, une archive ou un dossier avec des arguments passés en paramètre
	 * @param string $target Adresse du fichier/dossier
	 * @param array $args Arguments à passer en paramètre à FIDO
	 * @param string $redirectionFile Fichier vers lequel rediriger les résultats
	 * @return array réponse parsée
     *
     * @see Fido::_parseResponse
	 * @see Fido::_execute
	 */
	public static function analyze($target, $args = array(), $redirectionFile = '') {
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
	protected static function _execute($target, $args_list = array(), $redirectionFile = '') {
		$output = array();
		/**
		 * transformation de la liste d'arguments en chaine
		 * $args['-option'] = valeur devient "-option valeur"
		 */
		$args = " ";
		foreach ($args_list as $option => $value)
			$args .= $option . " " . $value . " ";

		// remplacement des espaces dans le chemin des fichiers
		$redirectionFile = str_replace(' ', '_', $redirectionFile);
		$target = escapeshellarg($target);

		$redirect = !empty($redirectionFile) ? " > " . escapeshellarg($redirectionFile) : '';
		// construction de la chaine de commande et supprimer les espaces multiples
		$command = preg_replace('!\s+!', ' ', 
				self::python . ' ' . self::fido . ' ' . $args . self::matchPrintf . self::nomatchPrintf . $target . $redirect);

		exec($command, $output);
		return self::_parseResponse($output, $redirectionFile);
	}

	/**
	 * Fonction de parsage de la réponse de fido à Fido::_execute
	 * @param array $responses Réponse de Fido
     * @param string $redirection Fichier vers lequel écrire le résultat (sortie standard par défaut)
	 * @return array Réponse parsée, example :
	 * [0] => Array
     *  (
     *      [result] => OK
	 *		[filename] => "chemin_du_fichier"
     *      [puid] => fmt/291
     *      [formatname] => "OpenDocument Text"
     *      [version] => "1.2"
     *      [signaturename] => "ODF 1.2 text"
     *      [mimetype] => "application/vnd.oasis.opendocument.text"
     *  ),
	 * [1] => Array
     *  (
     *      [result] => KO
	 *		[filename] => "chemin_du_fichier"
     *  )
	 */
	protected static function _parseResponse($responses, $redirection) {
		$parsed = array();
		$lines = empty($redirection) ? $responses : file($redirection);
		foreach ($lines as $line) {
			$data = explode(',', $line);
			if ($data[0] == "OK") {
				$parsed[] = array(
					'result'		=>	$data[0],
					'filename'		=>	$data[1],
					'puid'			=>	$data[2],
					'formatname'	=>	$data[3] == 'None' ? null : $data[3],
					'version'		=>	$data[4] == 'None' ? null : $data[4],
					'signaturename' =>	$data[5] == 'None' ? null : $data[5],
					'mimetype'		=>	$data[6] == 'None' ? null : $data[6],
				);
			} else {
				$parsed[] = array(
					'result'		=> $data[0],
					'filename'		=> $data[1],
				);
			}
		}
		return $parsed;
	}

}
