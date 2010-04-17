<?php
	defined('PUBWICH') or die('No direct access allowed.');

	/**
	 * @classname LastFM
	 * @description Fetch data from Last.fm
	 * @version 1.2 (20100526)
	 * @author Rémi Prévost (exomel.com)
	 * @methods LastFMWeeklyAlbums LastFMRecentTracks LastFMTopAlbums
	 */

	class LastFM extends Service {

		public $username, $size, $key, $classes, $compteur;

		public function setVariables( $config ) {
			$this->compteur = 0;
			$this->username = $config['username'];
			$this->key = $config['key'];
			$this->total = $config['total'];
			$this->setURLTemplate('http://www.last.fm/user/'.$config['username'].'/');
		}

		public function buildCache($Cache_Lite = null) {
			parent::buildCache($Cache_Lite);
		}

		public function getData() {
			return parent::getData();
		}

		public function init() {
			parent::init();
		}

	}

	class LastFMRecentTracks extends LastFM {

		public function __construct( $config ) {
			parent::setVariables( $config );

			$this->setURL( sprintf( 'http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&api_key=%s&user=%s&limit=%d', $this->key, $this->username, $this->total ) );
			$this->setItemTemplate('<li><a href="{{{link}}}"><strong>{{{track}}}</strong> — {{{artist}}}</a></li>'."\n");

			parent::__construct( $config );
		}

		/**
		 * @return SimpleXMLElement
		 */
		public function getData() {
			$data = parent::getData();
			return $data->recenttracks->track;
		}

		/**
		 * @return array
		 */
		public function populateItemTemplate( &$item ) {
			$album = $item->album;
			$artist = $item->artist;
			$title= $item->name;
			$this->compteur++;
			return array(
						'link' => htmlspecialchars( $item->url ),
						'artist' => $artist,
						'album' => $album,
						'track' => $title,
						'date' => $item->date,
						);
		}

	}
	
	class LastFMWeeklyTracks extends LastFM {

		public function __construct( $config ) {
			parent::setVariables( $config );	
			$this->classes = array( 'premier', 'deuxieme', 'troisieme', 'quatrieme' );

			$this->setURL( sprintf( 'http://ws.audioscrobbler.com/2.0/?method=user.getweeklytrackchart&api_key=%s&user=%s', $this->key, $this->username ) );
			$this->setItemTemplate('<li{%classe%}><a title="{%playcount%} plays" class="clearfix" href="{%link%}"><img src="{%image%}" width="{%size%}" height="{%size%}" alt="{%track%}"><strong><span>{%artist%}</span> {%track%}</strong></a></li>'."\n");

			parent::__construct( $config );
		}

		/**
		 * @return SimpleXMLElement
		 */
		public function getData() {
			$data = parent::getData();
			return $data->weeklytrackchart->track;
		}

		/**
		 * @return array
		 */
		public function populateItemTemplate( &$item ) {
			$album = $item->album;
			$artist = $item->artist;
			$title= $item->name;
			$this->compteur++;
			
			$img = ($item->image[2] != '' ? $item->image[2] : Pubwich::getThemeUrl().'/img/cover.png');
			
			return array(
						'link' => htmlspecialchars( $item->url ),
						'artist' => $artist,
						'album' => $album,
						'track' => $title,
						'date' => $item->date,
						'image' => $img,
						'size' => $this->size,
						'playcount' => $item->playcount,
						'classe' => isset($this->classes[$this->compteur-1]) ? ' class="'.$this->classes[$this->compteur-1].'"' : '',
						);
		}

	}
	

	class LastFMWeeklyAlbums extends LastFM {

		public function __construct( $config ){
			parent::setVariables( $config );	

			$this->setURL( sprintf( 'http://ws.audioscrobbler.com/2.0/?method=user.getweeklyalbumchart&api_key=%s&user=%s', $this->key, $this->username ) );
			$this->classes = array( 'premier', 'deuxieme', 'troisieme', 'quatrieme' );
			$this->setItemTemplate('<li{{{classe}}}><a href="{{{link}}}"><img src="{{{image}}}" width="{{{size}}}" height="{{{size}}}" alt="{{{title}}}"><strong>{{{artist}}}</strong> {{{album}}}</a></li>'."\n");

			parent::__construct( $config );
		}

		public function setVariables( $config ) {
            parent::setVariables($config);
			$this->size = $config['size'];
		}

		/**
		 * @param string $url
		 * @return void
		 */
		public function buildCache($Cache_Lite = null) {
			parent::buildCache($Cache_Lite);
			$this->buildAlbumCache( true );
		}

		/**
		 * @param bool $rebuildCache Force cache rebuild
		 * @return void
		 */
		public function buildAlbumCache( $rebuildCache ) {
			$data = $this->getData();
			$compteur = 0;
			if ( $data ) {
				foreach ( $this->getData() as $album ) {
					$compteur++;
					if ($compteur > $this->total) { break; }
					$this->fetchAlbum( $album, $rebuildCache );
				}
			}
		}

		/**
		 * @param SimpleXMLElement $album
		 * [@param bool $rebuildCache]
		 * @return void
		 */
		public function fetchAlbum($album, $rebuildCache=false) {
			$Cache_Lite = new Cache_Lite( parent::getCacheOptions() );
			$id = $this->buildAlbumId( $album );
			if ( !$rebuildCache && $data = $Cache_Lite->get( $id ) ) {
				$this->albumdata[$id] = simplexml_load_string( $data );
			} else {
				$Cache_Lite->get( $id );
				PubwichLog::log( 2, Pubwich::_( 'Rebuilding cache for a Last.fm album' ) );
				$url = sprintf( "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=%s&artist=%s&album=%s", $this->key, urlencode( $album->artist ), urlencode( $album->name ) );
				$data = FileFetcher::get( $url );
				$cacheWrite = $Cache_Lite->save( $data );
				if ( PEAR::isError($cacheWrite) ) {
					//var_dump( $cacheWrite );
				}
				$this->albumdata[$id] = simplexml_load_string( $data );
			}

		}

		/**
		 * @param SimpleXMLElement $album
		 * @return string
		 */
		public function buildAlbumId($album) {
			return md5( $album->artist . "|" . $album->name );
		}

		/**
		 * @param SimpleXMLElement $album
		 * @return string
		 */	
		public function getImage( $album ) {
			$id = $this->buildAlbumId( $album );
			$image = $this->albumdata[$id]->album->image[1];
			return ( $image == '' ) ? Pubwich::getThemeUrl().'/img/cover.png' : $image;
		}

		/**
		 * @return array
		 */
		public function populateItemTemplate( &$item ) {
			$album = $item->name;
			$artist = $item->artist;
			$this->compteur++;
			return array(
						'link' => htmlspecialchars( $item->url ),
						'title' => ( $artist . ' — ' . $album ),
						'artist' => $artist,
						'release_date' => $item->release_date,
						'listeners' => $item->listeners,
						'playcount' => $item->playcount,
						'album' => $album,
						'image' => $this->getImage( $item ),
						'size' => $this->size,
						'classe' => isset($this->classes[$this->compteur-1]) ? ' class="'.$this->classes[$this->compteur-1].'"' : '',
						);
		}

		/**
		 * @var $albumdata
		 */
		public $albumdata;

		/**
		 * @return SimpleXMLElement
		 */
		public function getData() {
			$data = parent::getData();
			return $data->weeklyalbumchart->album;
		}

		/**
		 * @param SimpleXMLElement $album
		 * @return string
		 */
		public function getYear( $album ) {
			$id = $this->buildAlbumId( $album );
			$date = $this->albumdata[$id]->album->releasedate;
			return $date;
		}

		/**
		 * @param string $url
		 * @return LastFMWeeklyAlbums
		 */
		public function init() {
			parent::init();
			$this->buildAlbumCache( false );
			return $this;
		}

	}

	class LastFMTopAlbums extends LastFM {
		public function __construct( $config ) {
			parent::setVariables( $config );
			$period = isset($config['period']) ? $config['period'] : 'overall';
			$this->setURL( sprintf( 'http://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&api_key=%s&user=%s&period=%s', $this->key, $this->username, $period ) );
			$this->setItemTemplate('<li><a href="{{{link}}}"><img class="item-media-thumbnail" src="{{{image_medium}}}" width="{{{size}}}" height="{{{size}}}" alt="{{{title}}}"><strong>{{{artist}}}</strong> {{{album}}}</a></li>'."\n");
			parent::__construct( $config );
		}

		public function setVariables( $config ) {
            parent::setVariables($config);
			$this->size = $config['size'];
		}

		/**
		 * @return SimpleXMLElement
		 */
		public function getData() {
			$data = parent::getData();
			return $data->topalbums->album;
		}

		/**
		 * @return array
		 */
		public function populateItemTemplate( &$item ) {
			$images = new StdClass;
			foreach( $item->image as $k=>$i ) {
				$key = (string) $i['size'];
				$val = (string) $i;
				$images->{$key} = $val;
			}
			return array(
						'size' => $this->size,
						'link' => $item->url,
						'playcount' => $item->playcount,
						'album' => $item->name,
						'artist' => $item->artist->name,
						'image_small' => $images->small,
						'image_medium' => $images->medium,
						'image_large' => $images->large,
						'image_extralarge' => $images->extralarge,
						);
		}
	}
