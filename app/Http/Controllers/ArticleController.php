<?php

namespace App\Http\Controllers;

use App\Repositories\ArticleRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use App\Repositories\CategoryRepository;
use App\User;
use App\Http\Requests;
use Illuminate\Support\Facades\Redirect;

class ArticleController extends PostController
{
    /**
     * Type of the post
     */
    const TYPE = PostController::POST_ARTICLE;

    /**
     * ArticleController constructor.
     * @param ArticleRepository $repository
     * @param CategoryRepository $categoryRepository
     * @param UserRepository $userRepository
     */
    public function __construct(ArticleRepository $repository, CategoryRepository $categoryRepository, UserRepository $userRepository)
    {
        parent::__construct($repository, $categoryRepository, $userRepository);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
            /*  Get articles of a concrete user */
            $author = $this->userRepository->findUserByUsername($username);
            $posts = $this->repository->findArticles($author);
        } else {
            /* Get all articles */
            $posts = $this->repository->findAllArticles(self::POSTS_PAGINATION_NUMBER);
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
        $categories = $this->categoryRepository->all();
        $type = self::TYPE;
        return view('home.posts.article', compact('categories', 'type'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $type = self::TYPE)
    {
        $result = parent::store($request, $type);
        if (!$result['error']) {
            return Redirect::to('home/articles/edit/' . $result['id'])->withSuccess($result['messages']);
        } else {
            return Redirect::to('home/articles/create')->withErrors($result['messages']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param string $slug
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        $post = $this->repository->findArticleBySlug($slug);
        return view('themes.' . IndexController::THEME . '.blog.singlearticle', compact('post'));
    }

    /**
     * Display list of posts by username.
     *
     * @param string $username
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showByUsername($username)
    {
        $author = $this->userRepository->findUserByUsername($username);
        $posts = $this->repository->findPublishedArticlesByAuthor($author, self::POSTS_PUBLIC_PAGINATION_NUMBER);
        return view('themes.' . IndexController::THEME . '.userposts', compact('posts', 'author'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post = $this->repository->findOrFail($id);
        $categories = $this->categoryRepository->all();
        return view('home.posts.article', compact('categories', 'post'));
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
        $result = parent::update($request, $id);
        if (!$result['error']) {
            return Redirect::to('home/articles/edit/' . $id)->withSuccess($result['messages']);
        } else {
            return Redirect::to('home/articles/edit/' . $id)->withErrors($result['messages']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
