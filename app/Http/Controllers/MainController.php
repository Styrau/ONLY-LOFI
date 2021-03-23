<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\User;
use App\Models\Comment;
use App\Models\Playlist;
use App\Models\PlaylistSong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Response;


class MainController extends Controller
{

    public function main() {
        $PlastsListened = Playlist::select('*','playlist.id as idPlaylist')->join('listened','idListened', '=', 'playlist.id')->where('playlist.user_id','=', Auth::id())->where('playlist','=', 1)->orderBy('listened.id', 'DESC')->limit(4)->get();
        $PlastsListened = $PlastsListened->unique('idPlaylist');

        return view("page.main", ["PlastsListened" => $PlastsListened]);
    }

    public function upload() {
        return view("page.upload");
    }

    public function likes() {
        $allLikes = Song::select('*')->join('likes','idSong','=','song.id')->where('idLikeur','=', Auth::id())->orderBy('likes.id', 'DESC')->get();
        return view("page.defaultAll", ["collection" => $allLikes]);
    }

    public function playlists() {
        $playlists = Playlist::select('*','playlist.id as idPlaylist')->where('user_id','=', Auth::id())->limit(4)->orderBy('playlist.id', 'DESC')->get();
        return view("page.defaultAll", ["collection" => $playlists]);
    }

    public function song() {
             
        $PlastsListened = Playlist::select('*','playlist.id as idPlaylist')->join('listened','idListened', '=', 'playlist.id')->where('playlist.user_id','=', Auth::id())->where('playlist','=', 1)->orderBy('listened.id', 'DESC')->limit(4)->get();
        $PlastsListened = $PlastsListened->unique('idPlaylist');

        $PlastsCreated = Playlist::select('*','playlist.id as idPlaylist')->where('user_id','=', Auth::id())->limit(4)->orderBy('playlist.id', 'DESC')->get();

        $SlastsListened = Song::select('*','song.id as idSong')->join('listened', 'idListened', '=', 'song.id')->where('idListener','=', Auth::id())->where('playlist','=', 0)->limit(4)->orderBy('listened.id', 'DESC')->get();
        $SlastsListened = $SlastsListened->unique('idSong');
        
        $SlastsLikes = Song::select('*')->join('likes','idSong','=','song.id')->where('idLikeur','=', Auth::id())->orderBy('likes.id', 'DESC')->limit(4)->get();

        return view("page.library", ["PlastsListened" => $PlastsListened, "PlastsCreated" => $PlastsCreated, "SlastsListened" => $SlastsListened, "SlastsLikes" => $SlastsLikes]);
    }

    public function store(Request $request) {

        $request->validate([
            'song_title' => "required|min:4|max:255",
            'song_file' => 'required|file|mimes:mp3,ogg'
        ]);

        $name = $request->file('song_file')->hashName();
        $request->file('song_file')->move("uploads/".Auth::id(), $name);

        $song = new Song();
        $song->title = $request->input('song_title');
        $song->url = "/uploads/".Auth::id()."/".$name;

        $song->img = "/assets/redswankurochuu.png";
        $song->user_id = Auth::id();

        $song->save();

        return redirect("upload");
    }

    public function songId($id) { 
        $song = Song::findOrFail($id);
        $uploaderName = User::select('name')->where('id', '=', $song->user_id)->get();
        
        $comments = Comment::join('users','comments.idWriter', '=', 'users.id')->where('idPost', '=', $id)->get();
        $nbComments = count($comments);
        
        return view("page.song", ["song" => $song, "artist" => $uploaderName, "comments" => $comments, "nbComments" => $nbComments, "playlist" => false]);
    }

    public function playlistId($id) { 
        $playlist = Playlist::findOrFail($id);
        $uploaderName = User::select('name')->where('id', '=', $playlist->user_id)->get();

        $playlistContent = [];
        $artists = [];
        $playlistContentTable = PlaylistSong::all()->where('idPlaylist', '=', $id);

        foreach ($playlistContentTable as $songs) {
            array_push($playlistContent, Song::select('*','song.id AS idsong')->join('users', 'song.user_id', '=', 'users.id')->where('song.id', '=', $songs->idSong)->first());
        }

        return view("page.song", ["song" => $playlist, "artist" => $uploaderName, "comments" => "none", "nbComments" => "none", "playlist" => true, "playlistContent" => $playlistContent]);
    }

    public function user() {
        return view("page.user");
    }

    public function userId($id) {
        $user = User::findOrFail($id);
        $social = ['youtube', 'soundcloud', 'twitter', 'instagram'];
        return view("page.user", ["user" => $user, "social" => $social]);
    }

    public function addComment($id, Request $request) {
        $request->validate([
            'content' => 'required|min:7|max:500',
        ]);

        $comment = new Comment();
        $comment->idWriter = Auth::id();
        $comment->idPost = $id;
        $comment->content = $request->input('content');
        $comment->save();

        return redirect("/song/$id");

    }

    public function search($search) {
        $songs = Song::whereRaw("title LIKE CONCAT('%', ?, '%')", [$search])->get();
        $playlists = Playlist::whereRaw("title LIKE CONCAT('%', ?, '%')", [$search])->get();
        $users = User::whereRaw("name LIKE CONCAT('%', ?, '%')", [$search])->get();

        return view('page.search', ['search' => $search, 'users' => $users, 'songs' => $songs, 'playlists' => $playlists]);
    }

    public function changeLike($id) {
        Auth::user()->ILikeThem()->toggle($id);
        return back();
    }

    public function render($id, $file) {
        $song = Song::find($id);
        $file = ".".$song->url;
        $mime_type = "audio/mp3";
        $fileContents = File::get($file);

        return Response::make($fileContents, 200)
            ->header('Accept-Ranges', 'bytes')
            ->header('Content-Type', $mime_type)
            ->header('Content-Length', filesize($file))
            ->header('vary', 'Accept-Encoding');
        }
}
