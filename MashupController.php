<?php
// Une modifiation bidon
namespace DB\MashupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use DB\MashupBundle\Entity\Map;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use DB\MashupBundle\Event\Events;
use DB\MashupBundle\Event\ParserMapEvent;

class MashupController extends Controller {
	public function mapAction() {
		return $this->render ( 'DBMashupBundle:Mashup:mashup.html.twig' );
	}
	public function mapShowAction() {
		return $this->render ( 'DBMashupBundle:Mashup:show.html.twig', array (
				'maps' => $this->getMapsOfUser () 
		) );
	}
	public function mapFilterAction($mapId) {
		$em = $this->getDoctrine ()->getManager ();
		$data = array ();
		$map = $em->getRepository ( 'DBMashupBundle:Map' )->findOneBy ( array (
				'mapId' => $mapId 
		) );
		
		// Si la map n'existe pas en BDD ou l'utilisateur n'est pas le bon
		// on renvoie un 403
		if ($map == null || $map->getUser () != $this->getUser ()) {
			throw new AccessDeniedException ();
		} 

		else {
			$infoWindowTemplate = ($map->getType () == 'c') ? 'DBMashupBundle:Mashup:infowindow_conference.html.twig' : 'DBMashupBundle:Mashup:infowindow_auteur.html.twig';
			
			// On traite chaque édition de conférence comme un marqueur
			foreach ( $map->getConferenceEditions () as $conferenceEdition ) {
				$location = $conferenceEdition->getLocation ();
				$lat = $location->getLat ();
				$lng = $location->getLng ();
				$address = $location->getAddress ();
				$date = $conferenceEdition->getDate ()->format ( 'Y' );
				// $title = $conferenceEdition->getConference()->getName() . ' (' . $date . ')';
				
				$content = $this->get ( 'twig' )->render ( $infoWindowTemplate, array (
						"conferenceEdition" => $conferenceEdition,
						"author" => $map->getAuthor () 
				) );
				
				$data [] = array (
						'lat' => $lat,
						'lng' => $lng,
						'location' => $address,
						'date' => $date,
						// 'title' => $title,
						'content' => $content 
				);
			}
		}
		// On renvoie le template rendu avec la variable des conférences "en dur"
		
		return $this->render ( 'DBMashupBundle:Mashup:map_filter.html.twig', array (
				'json_map_content' => json_encode ( $data ),
				'maps' => $this->getMapsOfUser (),
				'mapId' => $map->getMapId () 
		) );
	}
	
	// Permet de récupèrer les cartes associées à un utilisateur
	private function getMapsOfUser() {
		$user = $this->getUser ();
		$maps = array ();
		if ($user != null) {
			$maps = $user->getMaps ();
		}
		return $maps;
	}
	public function ajaxCheckConferenceAction() {
		$nomConference = $this->getRequest ()->get ( 'nomConference' );
		$link = "http://dblp.uni-trier.de/search/venue?q=" . $nomConference;
		
		libxml_use_internal_errors ( true );
		ini_set ( 'user_agent', 'PHP' );
		
		$domConf = new \DOMDocument ();
		$domConf->loadHTMLFile ( $link );
		$xpathConf = new \DOMXPath ( $domConf );
		// Le titre de la page est fonction du résultat de la recherche:
		// <title>dblp: Venue search for [...]</title> -> aucun resultat [précis] trouvé
		$title = $xpathConf->query ( "//head/title" )->item ( 0 )->nodeValue;
		$response = new JsonResponse ();
		if (strpos ( $title, 'dblp: Venue search for' ) !== FALSE) {
			$alternative = array ();
			// DBLP retourne 5 résultats lexicalement proche de la saisie
			$alternativesConf = $xpathConf->query ( "//div[@id='main']/div[@class='section' and not(@id)]/div/ul[1]/li/a" );
			// Récupération du nom et du lien de chaque conférence lexicalement proche
			for($i = 0; $i < $alternativesConf->length; $i ++) {
				array_push ( $alternative, array (
						'conf' => $alternativesConf->item ( $i )->nodeValue,
						'url' => $alternativesConf->item ( $i )->getAttribute ( 'href' ) 
				) );
			}
			$response->setData ( array (
					'response' => 'KO' . $alternativesConf->length . " " . $i,
					'data' => $alternative 
			) );
		} else {
			// La page de la conférence est directement renvoyée
			$response->setData ( array (
					'response' => 'OK',
					'data' => $xpathConf->query ( "//head/link[@rel= 'canonical']/@href" )->item ( 0 )->nodeValue 
			) );
		}
		return $response;
	}
	public function ajaxParserConferenceAction() {
		$link = $this->getRequest ()->get ( 'link' );
		
		// On cree et on declenche l'evenement pour parser et creer la map
		$event = new ParserMapEvent ( $link, $this->getUser () );
		$event->setType("conference");
		$this->get ( 'event_dispatcher' )
		->dispatch ( Events::parserMap, $event );
		
		$response = new JsonResponse ();
		return $response;
	}
	public function ajaxCheckAuthorAction() {
		$nom = $this->getRequest ()->get ( 'nom' );
		$prenom = $this->getRequest ()->get ( 'prenom' );
		
		$link = "http://dblp.uni-trier.de/search/author?author=" . $prenom . ":" . $nom;
		
		libxml_use_internal_errors ( true );
		ini_set ( 'user_agent', 'PHP' );
		
		$domAuthor = new \DOMDocument ();
		$domAuthor->loadHTMLFile ( $link );
		$xpathAuthor = new \DOMXPath ( $domAuthor );
		$title = $xpathAuthor->query ( "//head/title" )->item ( 0 )->nodeValue;
		$response = new JsonResponse ();
		// Le titre de la page est fonction du resultat de la recherche:
		// <title>dblp: Venue search for [...]</title> -> aucun resultat [precis] trouve
		// R. Ma est un auteur precis mais correspond à d'autres auteurs
		// Par consequent, on ne peut pas acceder a tous les auteurs simplement par leur nom et prenom
		// --> necessité de passer par les liens
		if (strpos ( $title, 'dblp: Author search for' ) !== FALSE) {
			// Problème: pas de résultat précis pour l'auteur spécifié
			$alternative = array ();
			// Récupération de l'identité des auteurs et des liens vers leur page
			$alternativesAuthor = $xpathAuthor->query ( "//div[@id='main']/div[@class='section' and not(@id)]/div/ul[1]/li/a/span" );
			$alternativeUrl = $xpathAuthor->query ( "//div[@id='main']/div[@class='section' and not(@id)]/div/ul[1]/li/a/@href" );
			for($i = 0; $i < $alternativesAuthor->length; $i ++) {
				array_push ( $alternative, array (
						'author' => $alternativesAuthor->item ( $i )->nodeValue,
						'url' => $alternativeUrl->item ( $i )->nodeValue 
				) );
			}
			$response->setData ( array (
					'response' => 'KO' . $alternativesAuthor->length . " " . $i,
					'data' => $alternative 
			) );
		} else {
			// La page de la conférence est directement renvoyée
			// Il faut récupérer le lien réel vers la page de l'auteur, pas le lien de recherche
			$response->setData ( array (
					'response' => 'OK',
					'data' => $xpathAuthor->query ( "//head/link[@rel= 'canonical']/@href" )->item ( 0 )->nodeValue 
			) );
		}
		return $response;
	}
	public function ajaxParserAuthorAction() {
		$link = $this->getRequest ()->get ( 'link' );
		
		// On cree et on declenche l'evenement pour parser et creer la map
		$event = new ParserMapEvent ( $link, $this->getUser () );
		$event->setType("author");
		$this->get ( 'event_dispatcher' )
		->dispatch ( Events::parserMap, $event );
		
		$response = new JsonResponse ();
		return $response;
	}
	public function ajaxGetMapAction(Request $request) {
		$em = $this->getDoctrine ()->getManager ();
		$data = array ();
		
		$status = 'OK';
		$mapId = $request->query->get ( 'map' );
		
		// On met à jour le status si le paramètre 'map' est null ou vide
		// Sinon on peut continuer
		if (empty ( $mapId )) {
			$status = 'NO_MAP_ID_PROVIDED';
		} else {
			$map = $em->getRepository ( 'DBMashupBundle:Map' )->findOneBy ( array (
					'mapId' => $mapId 
			) );
			
			// Si la map n'existe pas en BDD on met à jour le status
			// sinon on peut continuer
			if ($map == null) {
				$status = 'WRONG_MAP_ID';
			} else {
				$infoWindowTemplate = ($map->getType () == 'c') ? 'DBMashupBundle:Mashup:infowindow_conference.html.twig' : 'DBMashupBundle:Mashup:infowindow_auteur.html.twig';
				
				// On traite chaque édition de conférence comme un marqueur
				foreach ( $map->getConferenceEditions () as $conferenceEdition ) {
					$location = $conferenceEdition->getLocation ();
					$lat = $location->getLat ();
					$lng = $location->getLng ();
					$address = $location->getAddress ();
					$date = $conferenceEdition->getDate ()->format ( 'Y' );
					$title = $conferenceEdition->getConference ()->getName () . ' (' . $date . ')';
					
					$content = $this->get ( 'twig' )->render ( $infoWindowTemplate, array (
							"conferenceEdition" => $conferenceEdition,
							"author" => $map->getAuthor () 
					) );
					
					$data [] = array (
							'lat' => $lat,
							'lng' => $lng,
							'location' => $address,
							'date' => $date,
							'title' => $title,
							'content' => $content 
					);
				}
			}
		}
		
		// On renvoie une JsonResponse avec le status ('OK', 'NO_MAP_ID_PROVIDED' ou 'WRONG_MAP_ID')
		// ainsi que les données à afficher
		$response = new JsonResponse ();
		$response->setData ( array (
				'data' => $data,
				'status' => $status 
		) );
		
		return $response;
	}
}
	
