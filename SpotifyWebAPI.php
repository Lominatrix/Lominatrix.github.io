<?php
namespace SpotifyWebAPI;

class SpotifyWebAPI
{
    const RETURN_ASSOC = 'assoc';
    const RETURN_OBJECT = 'object';

    protected $accessToken = '';
    protected $lastResponse = [];
    protected $request = null;

    /**
     * Constructor
     * Set up Request object.
     *
     * @param Request $request Optional. The Request object to use.
     */
    public function __construct($request = null)
    {
        $this->request = $request ?: new Request();
    }

    /**
     * Add authorization headers.
     *
     * @return array Authorization headers.
     */
    protected function authHeaders()
    {
        $headers = [];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        return $headers;
    }

    /**
     * Convert Spotify object IDs to Spotify URIs.
     *
     * @param array|string $ids ID(s) to convert.
     * @param string $type Spotify object type.
     *
     * @return array|string Spotify URI(s).
     */
    protected function idToUri($ids, $type)
    {
        $type = 'spotify:' . $type . ':';

        $ids = array_map(function ($id) use ($type) {
            if (substr($id, 0, strlen($type)) != $type) {
                $id = $type . $id;
            }

            return $id;
        }, (array) $ids);

        return (count($ids) == 1) ? $ids[0] : $ids;
    }

    /**
     * Convert Spotify URIs to Spotify object IDs
     *
     * @param array|string $uriIds URI(s) to convert.
     * @param string $type Spotify object type.
     *
     * @return array|string Spotify ID(s).
     */
    protected function uriToId($uriIds, $type)
    {
        $type = 'spotify:' . $type . ':';

        $uriIds = array_map(function ($id) use ($type) {
            return str_replace($type, '', $id);
        }, (array) $uriIds);

        return (count($uriIds) == 1) ? $uriIds[0] : $uriIds;
    }

    /**
     * Add tracks to a playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/add-tracks-to-playlist/
     *
     * @param string $playlistId ID of the playlist to add tracks to.
     * @param string|array $tracks ID(s) or Spotify URI(s) of the track(s) to add.
     * @param array|object $options Optional. Options for the new tracks.
     * - int position Optional. Zero-based track position in playlist. Tracks will be appened if omitted or false.
     *
     * @return bool Whether the tracks was successfully added.
     */
    public function addPlaylistTracks($playlistId, $tracks, $options = [])
    {
        $options = http_build_query($options);

        $tracks = $this->idToUri($tracks, 'track');
        $tracks = json_encode((array) $tracks);

        $headers = $this->authHeaders();
        $headers['Content-Type'] = 'application/json';

        $playlistId = $this->uriToId($playlistId, 'playlist');

        // We need to manually append data to the URI since it's a POST request
        $uri = '/v1/playlists/' . $playlistId . '/tracks?' . $options;

        $this->lastResponse = $this->request->api('POST', $uri, $tracks, $headers);

        return $this->lastResponse['status'] == 201;
    }

    /**
     * Delete tracks from a playlist and retrieve a new snapshot ID.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/remove-tracks-playlist/
     *
     * @param string $playlistId ID or Spotify URI of the playlist to delete tracks from.
     * @param array $tracks An array with the key "tracks" containing arrays or objects with tracks to delete.
     * Or an array with the key "positions" containing integer positions of the tracks to delete.
     * For legacy reasons, the "tracks" key can be omitted but its use is deprecated.
     * If the "tracks" key is used, the following fields are also available:
     * - string id Required. Track ID or Spotify URI.
     * - int|array positions Optional. The track's position(s) in the playlist.
     * @param string $snapshotId Required when `$tracks['positions']` is used, optional otherwise.
     * The playlist's snapshot ID.
     *
     * @return string|bool A new snapshot ID or false if the tracks weren't successfully deleted.
     */
    public function deletePlaylistTracks($playlistId, $tracks, $snapshotId = '')
    {
        $options = [];

        if ($snapshotId) {
            $options['snapshot_id'] = $snapshotId;
        }

        if (isset($tracks['positions'])) {
            $options['positions'] = $tracks['positions'];
        } else {
            $tracks = isset($tracks['tracks']) ? $tracks['tracks'] : $tracks;

            $options['tracks'] = array_map(function ($track) {
                $track = (array) $track;

                if (isset($track['positions'])) {
                    $track['positions'] = (array) $track['positions'];
                }

                $track['uri'] = $this->idToUri($track['id'], 'track');

                unset($track['id']);

                return $track;
            }, $tracks);
        }

        $options = json_encode($options);

        $headers = $this->authHeaders();
        $headers['Content-Type'] = 'application/json';

        $playlistId = $this->uriToId($playlistId, 'playlist');

        $uri = '/v1/playlists/' . $playlistId . '/tracks';

        $this->lastResponse = $this->request->api('DELETE', $uri, $options, $headers);

        $body = $this->lastResponse['body'];

        if (isset($body->snapshot_id)) {
            return $body->snapshot_id;
        }

        return false;
    }

    /**
     * Get a album.
     * https://developer.spotify.com/documentation/web-api/reference/albums/get-album/
     *
     * @param string $albumId ID or Spotify URI of the album.
     *
     * @return array|object The requested album. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getAlbum($albumId)
    {
        $headers = $this->authHeaders();

        $albumId = $this->uriToId($albumId, 'album');
        $uri = '/v1/albums/' . $albumId;

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get multiple albums.
     * https://developer.spotify.com/documentation/web-api/reference/albums/get-several-albums/
     *
     * @param array $albumIds IDs or Spotify URIs of the albums.
     * @param array|object $options Optional. Options for the albums.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The requested albums. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getAlbums($albumIds, $options = [])
    {
        $albumIds = $this->uriToId($albumIds, 'album');

        $options = (array) $options;
        $options['ids'] = implode(',', (array) $albumIds);

        $headers = $this->authHeaders();

        $uri = '/v1/albums/';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get an album's tracks.
     * https://developer.spotify.com/documentation/web-api/reference/albums/get-albums-tracks/
     *
     * @param string $albumId ID or Spotify URI of the album.
     * @param array|object $options Optional. Options for the tracks.
     * - int limit Optional. Limit the number of tracks.
     * - int offset Optional. Number of tracks to skip.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The requested album tracks. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getAlbumTracks($albumId, $options = [])
    {
        $headers = $this->authHeaders();

        $albumId = $this->uriToId($albumId, 'album');
        $uri = '/v1/albums/' . $albumId . '/tracks';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist.
     * https://developer.spotify.com/documentation/web-api/reference/artists/get-artist/
     *
     * @param string $artistId ID or Spotify URI of the artist.
     *
     * @return array|object The requested artist. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getArtist($artistId)
    {
        $headers = $this->authHeaders();

        $artistId = $this->uriToId($artistId, 'artist');
        $uri = '/v1/artists/' . $artistId;

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get multiple artists.
     * https://developer.spotify.com/documentation/web-api/reference/artists/get-several-artists/
     *
     * @param array $artistIds IDs or Spotify URIs of the artists.
     *
     * @return array|object The requested artists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getArtists($artistIds)
    {
        $artistIds = $this->uriToId($artistIds, 'artist');
        $artistIds = implode(',', (array) $artistIds);

        $options = [
            'ids' => $artistIds,
        ];

        $headers = $this->authHeaders();

        $uri = '/v1/artists/';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist's related artists.
     * https://developer.spotify.com/documentation/web-api/reference/artists/get-related-artists/
     *
     * @param string $artistId ID or Spotify URI of the artist.
     *
     * @return array|object The artist's related artists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getArtistRelatedArtists($artistId)
    {
        $headers = $this->authHeaders();

        $artistId = $this->uriToId($artistId, 'artist');
        $uri = '/v1/artists/' . $artistId . '/related-artists';

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist's albums.
     * https://developer.spotify.com/documentation/web-api/reference/artists/get-artists-albums/
     *
     * @param string $artistId ID or Spotify URI of the artist.
     * @param array|object $options Optional. Options for the albums.
     * - string|array album_type Optional. Album type(s) to return. If omitted, all album types will be returned.
     * - string market Optional. Limit the results to items that are playable in this market, for example SE.
     * - int limit Optional. Limit the number of albums.
     * - int offset Optional. Number of albums to skip.
     *
     * @return array|object The artist's albums. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getArtistAlbums($artistId, $options = [])
    {
        $options = (array) $options;

        if (isset($options['album_type'])) {
            $options['album_type'] = implode(',', (array) $options['album_type']);
        }

        $headers = $this->authHeaders();

        $artistId = $this->uriToId($artistId, 'artist');
        $uri = '/v1/artists/' . $artistId . '/albums';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get an artist's top tracks in a country.
     * https://developer.spotify.com/documentation/web-api/reference/artists/get-artists-top-tracks/
     *
     * @param string $artistId ID or Spotify URI of the artist.
     * @param array|object $options Options for the tracks.
     * - string $country Required. An ISO 3166-1 alpha-2 country code specifying the country to get the top tracks for.
     *
     * @return array|object The artist's top tracks. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getArtistTopTracks($artistId, $options)
    {
        $headers = $this->authHeaders();

        $artistId = $this->uriToId($artistId, 'artist');
        $uri = '/v1/artists/' . $artistId . '/top-tracks';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get track audio features.
     * https://developer.spotify.com/documentation/web-api/reference/tracks/get-several-audio-features/
     *
     * @param array $trackIds IDs or Spotify URIs of the tracks.
     *
     * @return array|object The tracks' audio features. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getAudioFeatures($trackIds)
    {
        $trackIds = $this->uriToId($trackIds, 'track');
        $options = [
            'ids' => implode(',', (array) $trackIds),
        ];

        $headers = $this->authHeaders();

        $uri = '/v1/audio-features';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get audio analysis for track.
     * https://developer.spotify.com/documentation/web-api/reference/tracks/get-audio-analysis/
     *
     * @param string $trackId ID or Spotify URI of the track.
     *
     * @return object The track's audio analysis. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getAudioAnalysis($trackId)
    {
        $headers = $this->authHeaders();

        $trackId = $this->uriToId($trackId, 'track');
        $uri = '/v1/audio-analysis/' . $trackId;

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a list of categories used to tag items in Spotify (on, for example, the Spotify player’s "Browse" tab).
     * https://developer.spotify.com/documentation/web-api/reference/browse/get-list-categories/
     *
     * @param array|object $options Optional. Options for the categories.
     * - string locale Optional. Language to show categories in, for example sv_SE.
     * - string country Optional. An ISO 3166-1 alpha-2 country code. Show categories from this country.
     * - int limit Optional. Limit the number of categories.
     * - int offset Optional. Number of categories to skip.
     *
     * @return array|object The list of categories. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getCategoriesList($options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/browse/categories';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a single category used to tag items in Spotify (on, for example, the Spotify player’s "Browse" tab).
     * https://developer.spotify.com/documentation/web-api/reference/browse/get-category/
     *
     * @param string $categoryId The Spotify ID of the category.
     *
     * @param array|object $options Optional. Options for the category.
     * - string locale Optional. Language to show category in, for example sv_SE.
     * - string country Optional. An ISO 3166-1 alpha-2 country code. Show category from this country.
     *
     * @return array|object The category. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getCategory($categoryId, $options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/browse/categories/' . $categoryId;

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a list of Spotify playlists tagged with a particular category.
     * https://developer.spotify.com/documentation/web-api/reference/browse/get-categorys-playlists/
     *
     * @param string $categoryId The Spotify ID of the category.
     *
     * @param array|object $options Optional. Options for the category's playlists.
     * - string country Optional. An ISO 3166-1 alpha-2 country code. Show category playlists from this country.
     * - int limit Optional. Limit the number of playlists.
     * - int offset Optional. Number of playlists to skip.
     *
     * @return array|object The list of playlists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getCategoryPlaylists($categoryId, $options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/browse/categories/' . $categoryId . '/playlists';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get Spotify featured playlists.
     * https://developer.spotify.com/documentation/web-api/reference/browse/get-list-featured-playlists/
     *
     * @param array|object $options Optional. Options for the playlists.
     * - string locale Optional. Language to show playlists in, for example sv_SE.
     * - string country Optional. An ISO 3166-1 alpha-2 country code. Show playlists from this country.
     * - string timestamp Optional. A ISO 8601 timestamp. Show playlists relevant to this date and time.
     * - int limit Optional. Limit the number of playlists.
     * - int offset Optional. Number of playlists to skip.
     *
     * @return array|object The featured playlists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getFeaturedPlaylists($options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/browse/featured-playlists';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a list of possible seed genres.
     * https://developer.spotify.com/documentation/web-api/reference/browse/get-recommendations/
     *
     * @return array|object All possible seed genres. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getGenreSeeds()
    {
        $headers = $this->authHeaders();

        $uri = '/v1/recommendations/available-genre-seeds';

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get the latest full response from the Spotify API.
     *
     * @return array Response data.
     * - array|object body The response body. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     * - array headers Response headers.
     * - int status HTTP status code.
     * - string url The requested URL.
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Get the current user’s currently playing track.
     * https://developer.spotify.com/documentation/web-api/reference/player/get-the-users-currently-playing-track/
     *
     * @param array|object $options Optional. Options for the track.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The user's currently playing track. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getMyCurrentTrack($options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/player/currently-playing';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    public function validateAccessToken() {
        $this->getMyCurrentTrack();
        return (int)($this->lastResponse['status']) < 400;
    }

    /**
     * Get the current user’s current playback information.
     * https://developer.spotify.com/documentation/web-api/reference/player/get-information-about-the-users-current-playback/
     *
     * @param array|object $options Optional. Options for the info.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The user's playback information. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getMyCurrentPlaybackInfo($options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/player';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }


    /**
     * Get the current user’s playlists.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-a-list-of-current-users-playlists/
     *
     * @param array|object $options Optional. Options for the playlists.
     * - int limit Optional. Limit the number of playlists.
     * - int offset Optional. Number of playlists to skip.
     *
     * @return array|object The user's playlists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getMyPlaylists($options = [])
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/playlists';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
      * Get the current user’s recently played tracks.
      * https://developer.spotify.com/documentation/web-api/reference/player/get-recently-played/
      *
      * @param array|object $options Optional. Options for the tracks.
      * - int limit Optional. Number of tracks to return.
      * - string after Optional. Unix timestamp in ms (13 digits). Returns all items after this position.
      * - string before Optional. Unix timestamp in ms (13 digits). Returns all items before this position.
      *
      * @return array|object The most recently played tracks. Type is controlled by `SpotifyWebAPI::setReturnType()`.
      */
    public function getMyRecentTracks($options = [])
    {
        $options = (array) $options;

        $headers = $this->authHeaders();

        $uri = '/v1/me/player/recently-played';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a specific playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-playlist/
     *
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Optional. Options for the playlist.
     * - string|array fields Optional. A list of fields to return. See Spotify docs for more info.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The user's playlist. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getPlaylist($playlistId, $options = [])
    {
        $options = (array) $options;

        if (isset($options['fields'])) {
            $options['fields'] = implode(',', (array) $options['fields']);
        }

        $headers = $this->authHeaders();

        $playlistId = $this->uriToId($playlistId, 'playlist');

        $uri = '/v1/playlists/' . $playlistId;

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get the tracks in a playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-playlists-tracks/
     *
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Optional. Options for the tracks.
     * - string|array fields Optional. A list of fields to return. See Spotify docs for more info.
     * - int limit Optional. Limit the number of tracks.
     * - int offset Optional. Number of tracks to skip.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The tracks in the playlist. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getPlaylistTracks($playlistId, $options = [])
    {
        $options = (array) $options;

        if (isset($options['fields'])) {
            $options['fields'] = implode(',', (array) $options['fields']);
        }

        $headers = $this->authHeaders();

        $playlistId = $this->uriToId($playlistId, 'playlist');

        $uri = '/v1/playlists/' . $playlistId . '/tracks';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a value indicating the response body type.
     *
     * @return string A value indicating if the response body is an object or associative array.
     */
    public function getReturnType()
    {
        return $this->request->getReturnType();
    }

    /**
     * Get the Request object in use.
     *
     * @return Request The Request object in use.
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get a track.
     * https://developer.spotify.com/documentation/web-api/reference/tracks/get-track/
     *
     * @param string $trackId ID or Spotify URI of the track.
     * @param array|object $options Optional. Options for the track.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The requested track. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getTrack($trackId, $options = [])
    {
        $headers = $this->authHeaders();

        $trackId = $this->uriToId($trackId, 'track');
        $uri = '/v1/tracks/' . $trackId;

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get multiple tracks.
     * https://developer.spotify.com/documentation/web-api/reference/tracks/get-several-tracks/
     *
     * @param array $trackIds IDs or Spotify URIs of the tracks.
     * @param array|object $options Optional. Options for the albums.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The requested tracks. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getTracks($trackIds, $options = [])
    {
        $trackIds = $this->uriToId($trackIds, 'track');
        $options['ids'] = implode(',', (array) $trackIds);

        $headers = $this->authHeaders();

        $uri = '/v1/tracks/';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user.
     * https://developer.spotify.com/documentation/web-api/reference/users-profile/get-users-profile/
     *
     * @param string $userId ID or Spotify URI of the user.
     *
     * @return array|object The requested user. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getUser($userId)
    {
        $headers = $this->authHeaders();

        $userId = $this->uriToId($userId, 'user');
        $uri = '/v1/users/' . $userId;

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get a user's specific playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-playlist/
     *
     * @deprecated
     *
     * @param string $userId ID or Spotify URI of the user.
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Optional. Options for the playlist.
     * - string|array fields Optional. A list of fields to return. See Spotify docs for more info.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The user's playlist. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getUserPlaylist($userId, $playlistId, $options = [])
    {
        return $this->getPlaylist($playlistId, $options);
    }

    /**
     * Get a user's playlists.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-list-users-playlists/
     *
     * @param string $userId ID or Spotify URI of the user.
     * @param array|object $options Optional. Options for the tracks.
     * - int limit Optional. Limit the number of tracks.
     * - int offset Optional. Number of tracks to skip.
     *
     * @return array|object The user's playlists. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getUserPlaylists($userId, $options = [])
    {
        $headers = $this->authHeaders();

        $userId = $this->uriToId($userId, 'user');
        $uri = '/v1/users/' . $userId . '/playlists';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Get the tracks in a user's playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/get-playlists-tracks/
     *
     * @deprecated
     *
     * @param string $userId ID or Spotify URI of the user.
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Optional. Options for the tracks.
     * - string|array fields Optional. A list of fields to return. See Spotify docs for more info.
     * - int limit Optional. Limit the number of tracks.
     * - int offset Optional. Number of tracks to skip.
     * - string market Optional. An ISO 3166-1 alpha-2 country code, provide this if you wish to apply Track Relinking.
     *
     * @return array|object The tracks in the playlist. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function getUserPlaylistTracks($userId, $playlistId, $options = [])
    {
        return $this->getPlaylistTracks($playlistId, $options);
    }

    /**
     * Get the currently authenticated user.
     * https://developer.spotify.com/documentation/web-api/reference/users-profile/get-current-users-profile/
     *
     * @return array|object The currently authenticated user. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function me()
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me';

        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Check if albums are saved in the current user's Spotify library.
     * https://developer.spotify.com/documentation/web-api/reference/library/check-users-saved-albums/
     *
     * @param string|array $albums ID(s) or Spotify URI(s) of the album(s) to check for.
     *
     * @return array Whether each album is saved.
     */
    public function myAlbumsContains($albums)
    {
        $albums = $this->uriToId($albums, 'album');
        $albums = implode(',', (array) $albums);

        $options = [
            'ids' => $albums,
        ];

        $headers = $this->authHeaders();

        $uri = '/v1/me/albums/contains';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Check if tracks are saved in the current user's Spotify library.
     * https://developer.spotify.com/documentation/web-api/reference/library/check-users-saved-tracks/
     *
     * @param string|array $tracks ID(s) or Spotify URI(s) of the track(s) to check for.
     *
     * @return array Whether each track is saved.
     */
    public function myTracksContains($tracks)
    {
        $tracks = $this->uriToId($tracks, 'track');
        $tracks = implode(',', (array) $tracks);

        $options = [
            'ids' => $tracks,
        ];

        $headers = $this->authHeaders();

        $uri = '/v1/me/tracks/contains';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Play the next track in the current users's queue.
     * https://developer.spotify.com/documentation/web-api/reference/player/skip-users-playback-to-next-track/
     *
     * @param string $deviceId Optional. ID of the device to target.
     *
     * @return bool Whether the track was successfully skipped.
     */
    public function next($deviceId = '')
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/player/next';

        // We need to manually append data to the URI since it's a POST request
        if ($deviceId) {
            $uri = $uri . '?device_id=' . $deviceId;
        }

        $this->lastResponse = $this->request->api('POST', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Pause playback for the current user.
     * https://developer.spotify.com/documentation/web-api/reference/player/pause-a-users-playback/
     *
     * @param string $deviceId Optional. ID of the device to pause on.
     *
     * @return bool Whether the playback was successfully paused.
     */
    public function pause($deviceId = '')
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/player/pause';

        // We need to manually append data to the URI since it's a PUT request
        if ($deviceId) {
            $uri = $uri . '?device_id=' . $deviceId;
        }

        $this->lastResponse = $this->request->api('PUT', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Start playback for the current user.
     * https://developer.spotify.com/documentation/web-api/reference/player/start-a-users-playback/
     *
     * @param string $deviceId Optional. ID of the device to play on.
     * @param array|object $options Optional. Options for the playback.
     * - string context_uri Optional. Spotify URI of the context to play, for example an album.
     * - array uris Optional. Spotify track URIs to play.
     * - object offset Optional. Indicates from where in the context playback should start.
     *
     * @return bool Whether the playback was successfully started.
     */
    public function play($deviceId = '', $options = [])
    {
        $options = json_encode((object) $options);

        $headers = $this->authHeaders();
        $headers['Content-Type'] = 'application/json';

        $uri = '/v1/me/player/play';

        // We need to manually append data to the URI since it's a PUT request
        if ($deviceId) {
            $uri = $uri . '?device_id=' . $deviceId;
        } else {
            $playbackInfo = $this->getMyCurrentPlaybackInfo();
            
            if ($playbackInfo != null && $playbackInfo->device != null) {
                $uri = $uri . '?device_id=' . $playbackInfo->device->id;
            }
        }

        $this->lastResponse = $this->request->api('PUT', $uri, $options, $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Play the previous track in the current users's queue.
     * https://developer.spotify.com/documentation/web-api/reference/player/skip-users-playback-to-previous-track/
     *
     * @param string $deviceId Optional. ID of the device to target.
     *
     * @return bool Whether the track was successfully skipped.
     */
    public function previous($deviceId = '')
    {
        $headers = $this->authHeaders();

        $uri = '/v1/me/player/previous';

        // We need to manually append data to the URI since it's a POST request
        if ($deviceId) {
            $uri = $uri . '?device_id=' . $deviceId;
        }

        $this->lastResponse = $this->request->api('POST', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Reorder the tracks in a playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/reorder-playlists-tracks/
     *
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Options for the new tracks.
     * - int range_start Required. Position of the first track to be reordered.
     * - int range_length Optional. The amount of tracks to be reordered.
     * - int insert_before Required. Position where the tracks should be inserted.
     * - string snapshot_id Optional. The playlist's snapshot ID.
     *
     * @return string|bool A new snapshot ID or false if the tracks weren't successfully reordered.
     */
    public function reorderPlaylistTracks($playlistId, $options)
    {
        $options = json_encode($options);

        $headers = $this->authHeaders();
        $headers['Content-Type'] = 'application/json';

        $playlistId = $this->uriToId($playlistId, 'playlist');

        $uri = '/v1/playlists/' . $playlistId . '/tracks';

        $this->lastResponse = $this->request->api('PUT', $uri, $options, $headers);
        $body = $this->lastResponse['body'];

        if (isset($body->snapshot_id)) {
            return $body->snapshot_id;
        }

        return false;
    }

    /**
     * Reorder the tracks in a user's playlist.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/reorder-playlists-tracks/
     *
     * @deprecated
     *
     * @param string $userId ID or Spotify URI of the user.
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param array|object $options Options for the new tracks.
     * - int range_start Required. Position of the first track to be reordered.
     * - int range_length Optional. The amount of tracks to be reordered.
     * - int insert_before Required. Position where the tracks should be inserted.
     * - string snapshot_id Optional. The playlist's snapshot ID.
     *
     * @return string|bool A new snapshot ID or false if the tracks weren't successfully reordered.
     */
    public function reorderUserPlaylistTracks($userId, $playlistId, $options)
    {
        return $this->reorderPlaylistTracks($playlistId, $options);
    }

    /**
     * Set repeat mode for the current user’s playback.
     * https://developer.spotify.com/documentation/web-api/reference/player/set-repeat-mode-on-users-playback/
     *
     * @param array|object $options Optional. Options for the playback repeat mode.
     * - string state Required. The repeat mode. See Spotify docs for possible values.
     * - string device_id Optional. ID of the device to target.
     *
     * @return bool Whether the playback repeat mode was successfully changed.
     */
    public function repeat($options)
    {
        $options = http_build_query($options);

        $headers = $this->authHeaders();

        // We need to manually append data to the URI since it's a PUT request
        $uri = '/v1/me/player/repeat?' . $options;

        $this->lastResponse = $this->request->api('PUT', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Replace all tracks in a playlist with new ones.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/replace-playlists-tracks/
     *
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param string|array $tracks ID(s) or Spotify URI(s) of the track(s) to add.
     *
     * @return bool Whether the tracks was successfully replaced.
     */
    public function replacePlaylistTracks($playlistId, $tracks)
    {
        $tracks = $this->idToUri($tracks, 'track');
        $tracks = json_encode([
            'uris' => (array) $tracks,
        ]);

        $headers = $this->authHeaders();
        $headers['Content-Type'] = 'application/json';

        $playlistId = $this->uriToId($playlistId, 'playlist');

        $uri = '/v1/playlists/' . $playlistId . '/tracks';

        $this->lastResponse = $this->request->api('PUT', $uri, $tracks, $headers);

        return $this->lastResponse['status'] == 201;
    }

    /**
     * Replace all tracks in a user's playlist with new ones.
     * https://developer.spotify.com/documentation/web-api/reference/playlists/replace-playlists-tracks/
     *
     * @deprecated
     *
     * @param string $userId ID or Spotify URI of the user.
     * @param string $playlistId ID or Spotify URI of the playlist.
     * @param string|array $tracks ID(s) or Spotify URI(s) of the track(s) to add.
     *
     * @return bool Whether the tracks was successfully replaced.
     */
    public function replaceUserPlaylistTracks($userId, $playlistId, $tracks)
    {
        return $this->replacePlaylistTracks($playlistId, $tracks);
    }

    /**
     * Search for an item.
     * https://developer.spotify.com/documentation/web-api/reference/search/search/
     *
     * @param string $query The term to search for.
     * @param string|array $type The type of item to search for.
     * @param array|object $options Optional. Options for the search.
     * - string market Optional. Limit the results to items that are playable in this market, for example SE.
     * - int limit Optional. Limit the number of items.
     * - int offset Optional. Number of items to skip.
     *
     * @return array|object The search results. Type is controlled by `SpotifyWebAPI::setReturnType()`.
     */
    public function search($query, $type, $options = [])
    {
        $type = implode(',', (array) $type);
        $options = array_merge((array) $options, [
            'q' => $query,
            'type' => $type,
        ]);

        $headers = $this->authHeaders();

        $uri = '/v1/search';

        $this->lastResponse = $this->request->api('GET', $uri, $options, $headers);

        return $this->lastResponse['body'];
    }

    /**
     * Change playback position for the current user.
     * https://developer.spotify.com/documentation/web-api/reference/player/seek-to-position-in-currently-playing-track/
     *
     * @param array|object $options Optional. Options for the playback seeking.
     * - string position_ms Required. The position in milliseconds to seek to.
     * - string device_id Optional. ID of the device to target.
     *
     * @return bool Whether the playback position was successfully changed.
     */
    public function seek($options)
    {
        $options = http_build_query($options);

        $headers = $this->authHeaders();

        // We need to manually append data to the URI since it's a PUT request
        $uri = '/v1/me/player/seek?' . $options;

        $this->lastResponse = $this->request->api('PUT', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }

    /**
     * Set the access token to use.
     *
     * @param string $accessToken The access token.
     *
     * @return void
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Set the return type for the response body.
     *
     * @param string $returnType One of the `SpotifyWebAPI::RETURN_*` constants.
     *
     * @return void
     */
    public function setReturnType($returnType)
    {
        $this->request->setReturnType($returnType);
    }

    /**
     * Set shuffle mode for the current user’s playback.
     * https://developer.spotify.com/documentation/web-api/reference/player/toggle-shuffle-for-users-playback/
     *
     * @param array|object $options Optional. Options for the playback shuffle mode.
     * - bool state Required. The shuffle mode. See Spotify docs for possible values.
     * - string device_id Optional. ID of the device to target.
     *
     * @return bool Whether the playback shuffle mode was successfully changed.
     */
    public function shuffle($options)
    {
        $options = (array) $options;
        $options['state'] = $options['state'] ? 'true' : 'false';
        $options = http_build_query($options);

        $headers = $this->authHeaders();

        // We need to manually append data to the URI since it's a PUT request
        $uri = '/v1/me/player/shuffle?' . $options;

        $this->lastResponse = $this->request->api('PUT', $uri, [], $headers);

        return $this->lastResponse['status'] == 204;
    }
    
    /**
     * Change playback volume for the current user.
     * https://developer.spotify.com/documentation/web-api/reference/player/set-volume-for-users-playback/
     *
     * @param array|object $options Optional. Options for the playback volume.
     * - int volume_percent Required. The volume to set.
     * - string device_id Optional. ID of the device to target.
     *
     * @return bool Whether the playback volume was successfully changed.
     */
    public function changeVolume($options)
    {        
        $options = http_build_query($options);
        $headers = $this->authHeaders();
        
        // We need to manually append data to the URI since it's a PUT request
        $uri = '/v1/me/player/volume?' . $options;
        $this->lastResponse = $this->request->api('PUT', $uri, [], $headers);
        return json_encode($this->lastResponse);
        return $this->lastResponse['status'] == 204;
    }
}
