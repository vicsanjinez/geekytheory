<?php

namespace App\Http\Controllers;

use App\Category;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Auth;
use App\Post;
use App\User;
use Illuminate\Support\Facades\Response;
use Validator;
use Illuminate\Support\Facades\Redirect;

class PostController extends Controller
{

    /**
     * Number of posts to show with pagination
     */
    const POSTS_PAGINATION_NUMBER = 10;

    /**
     * Possible statuses of a post
     */
    const POST_STATUS_PENDING = 'pending';
    const POST_STATUS_DRAFT = 'draft';
    const POST_STATUS_DELETED = 'deleted';
    const POST_STATUS_PUBLISHED = 'published';
    const POST_STATUS_SCHEDULED = 'scheduled';

    /**
     * Display a listing of posts.
     *
     * @param $username
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($username = null)
    {
        if ($username === null) {
            $posts = Post::paginate(self::POSTS_PAGINATION_NUMBER)->where('status', self::POST_STATUS_PUBLISHED)->sortByDesc('created_at');
        } else {
            $user = User::where('username', $username)->firstOrFail();
            $posts = $user->posts()->paginate(self::POSTS_PAGINATION_NUMBER)->where('status', self::POST_STATUS_PUBLISHED);
        }

        return view('');
    }

    /**
     * Display a listing of the posts in admin panel.
     *
     * @param null $username
     * @return \Illuminate\Http\Response
     */
    public function indexHome($username = null)
    {
        if (!empty($username)) {
            /*  Get posts of a concrete user */
            $user = User::where('username', $username)->firstOrFail();
            $posts = $user->posts()->paginate(self::POSTS_PAGINATION_NUMBER);
        } else {
            /* Get all posts */
            $posts = Post::paginate(self::POSTS_PAGINATION_NUMBER);
        }
        return view('home.posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new post.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();
        return view('home.posts.post', compact('categories'));
    }

    /**
     * Store a newly created post in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $slug = getAvailableSlug($request->title, (new Post())->getTable());

        $rules = array(
            'title' => 'required|unique:posts|max:255',
            'body' => 'required',
            'status' => 'in:' . self::POST_STATUS_PENDING . ','
                              . self::POST_STATUS_DRAFT   . ','
                              . self::POST_STATUS_PUBLISHED . ','
                              . self::POST_STATUS_SCHEDULED,
            'description' => 'required|max:170',
            'slug' => 'required|unique:posts',
        );

        /** @var UploadedFile $image */
        $image = $request->file('image');

        $requestParams = array(
            'title'          => $request->title,
            'body'          => $request->body,
            'description'   => $request->description,
            'status'        => $request->status,
            'tags'          => $request->tags,
            'slug'          => $slug,
            'categories'    => $request->categories,
            'image'         => $image,
        );

        $validator = Validator::make($requestParams, $rules);

        if ($validator->fails()) {
            return Redirect::to('home/posts/create')->withErrors($validator->messages());
        } else {
            $post = new Post;
            $post->title = $requestParams['title'];
            $post->body = $requestParams['body'];
            $post->description = $requestParams['description'];
            $post->status = $requestParams['status'];
            $post->slug = $slug;
            $post->user_id = Auth::user()->id;
            if ($image) {
                $fileName = ImageManagerController::getImageName($image, ImageManagerController::PATH_IMAGE_UPLOADS);
                $post->image = $fileName;
                $image->move(ImageManagerController::PATH_IMAGE_UPLOADS, $fileName);
            }
            $post->save();
            $categories = Category::whereIn('id', $requestParams['categories'])->get();
            $post->categories()->sync($categories);
        }

        return Redirect::to('home/posts/edit/' . $post->id)->withSuccess(trans('home.tag_create_success'));
    }

    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        return view('themes.' . IndexController::THEME . '.blog.singlepost', compact('post'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post = Post::findOrFail($id);
        $categories = Category::all();
        return view('home.posts.post', compact('categories', 'post'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = array(
            'title' => 'required|max:255unique:posts,id,' . $id,
            'body' => 'required',
            'status' => 'in:' . self::POST_STATUS_PENDING . ','
                . self::POST_STATUS_DRAFT   . ','
                . self::POST_STATUS_PUBLISHED . ','
                . self::POST_STATUS_SCHEDULED,
            'description' => 'required|max:170',
        );

        $requestParams = array(
            'title' => $request->title,
            'body'  => $request->body,
            'description' => $request->description,
            'status' => $request->status,
            'tags' => $request->tags,
            'categories' => $request->categories,
        );

        $validator = Validator::make($requestParams, $rules);

        if ($validator->fails()) {
            return Redirect::to('home/posts/create')->withErrors($validator->messages());
        } else {
            $post = Post::findOrFail($id);
            $post->title = $requestParams['title'];
            $post->body = $requestParams['body'];
            $post->description = $requestParams['description'];
            $post->status = $requestParams['status'];
            $post->save();
            $categories = Category::whereIn('id', $requestParams['categories'])->get();
            $post->categories()->sync($categories);
        }

        return Redirect::to('home/posts/edit/' . $post->id)->withSuccess(trans('home.tag_create_success'));
    }

    /**
     * Set post status as draft after being deleted
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id)
    {
        $post = Post::findOrFail($id);
        $post->status = Post::STATUS_DRAFT;
        $post->save();
        return Redirect::back();
    }

    /**
     * Set post status as deleted.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $post = Post::findOrFail($id);
        $post->status = Post::STATUS_DELETED;
        $post->save();
        return Redirect::back();
    }

    /**
     * Delete the image of a post.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deletePostImage(Request $request)
    {
        if(!empty($request->id)) {
            $post = Post::findOrFail($request->id);
            $post->image = NULL;
            $post->save();
            return response()->json(['error' => 0]);
        } else {
            return response()->json(['error' => 1]);
        }

    }

}