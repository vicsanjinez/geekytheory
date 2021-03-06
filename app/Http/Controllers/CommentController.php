<?php

namespace App\Http\Controllers;

use App\Comment;
use App\Post;
use App\Repositories\CommentRepository;
use App\Repositories\PostRepository;
use App\Repositories\SiteMetaRepository;
use App\User;
use App\Validators\CommentValidator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    /** @var CommentRepository $commentRepository */
    private $commentRepository;

    /** @var PostRepository $postRepository */
    private $postRepository;

    /** @var SiteMetaRepository $siteMetaRepository */
    private $siteMetaRepository;

    /** @var  CommentValidator $commentValidator */
    private $commentValidator;

    /**
     * CommentController constructor.
     *
     * @param CommentRepository $commentRepository
     * @param PostRepository $postRepository
     * @param SiteMetaRepository $siteMetaRepository
     * @param CommentValidator $commentValidator
     */
    public function __construct(CommentRepository $commentRepository,
								PostRepository $postRepository,
								SiteMetaRepository $siteMetaRepository,
								CommentValidator $commentValidator)
    {
        $this->commentRepository = $commentRepository;
        $this->postRepository = $postRepository;
        $this->siteMetaRepository = $siteMetaRepository;
        $this->commentValidator = $commentValidator;
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function store(Request $request)
    {
        $data = array(
            'post_id' => $request->postId,
            'author_name' => $request->authorName,
            'author_email' => $request->authorEmail,
            'author_url' => $request->authorUrl,
            'body' => $request->body,
        );

        /** @var Post $post */
        $post = $this->postRepository->find($data['post_id']);
        if ($post === null) {
            return array(
                'error' => 1
            );
        }

        if (!empty($request->parent)) {
            $data['parent'] = $request->parent;
        }

        /** @var User $user */
        $user = Auth::user();
        if ($user !== null) {
            $data['user_id'] = $user->getAttribute('id');
        }

        $data['ip'] = getClientIPAddress();

        $spam = false;
        if (AkismetController::getInstance()->verifyKey()) {
            $akismetCheckerData = $data + array(
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                    'referrer' => $_SERVER['HTTP_REFERER'],
                    'commentType' => 'comment',
                    'permalink' => $this->siteMetaRepository->getSiteMeta()->url . PostController::getPostPublicUrlByType($post),
                );
            $spam = AkismetController::getInstance()->commentIsSpam($akismetCheckerData);
        }

        $data['spam'] = $spam;

        $approved = !$spam;
        $data['approved'] = $approved;

        if (!$this->commentValidator->with($data)->passes()) {

            return array(
                'error' => 1,
                'message' => $this->commentValidator->errors(),
            );
        } else {
            $comment = $this->commentRepository->create($data);

            /*
             * Clear the comments cache of a single post.
             */
            if (Cache::has('comments_' . $post->slug)) {
                Cache::forget('comments_' . $post->slug);
            }

            /**
			 * If the new comment is an answer to another comment. Send a notification to the parent comment.
			 */
            if (!empty($data['parent'])) {
            	$commentParent = $this->commentRepository->find($data['parent']);
				try {
					// CommentEmailController::notifyResponse($post, $commentParent);
				} catch (\Exception $e) {
					// Just do not send the email.
				}
			}

            return [
                'error' => 0,
                'spam' => $data['spam'] ? 1 : 0,
                'html' => view('themes.vortex.partials.blog.singleComment', compact('comment'))->render(),
            ];
        }
    }

    /**
     * Get the form to send a new comment.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function getForm(Request $request)
    {
        $data = array(
            'parent' => $request->parent,
        );

        $comment = $this->commentRepository->find($data['parent']);

        if ($comment === null) {
            return ['error' => 1];
        }

        $post = $this->postRepository->find($comment->post_id);
        $commentParent = $data['parent'];

        return view('themes.vortex.partials.blog.commentForm', compact('post', 'commentParent'));
    }

    /**
     * Sort array of comments.
     *
     * @param $comments
     * @param bool $addChildToParent
     * @return array
     */
    public static function sortByParent($comments, $addChildToParent = true)
    {
        if (!$addChildToParent) {
            return $comments;
        }

        $sort = [];
        foreach ($comments as $comment) {
            $comment->children = [];
            $sort[$comment->id] = $comment;
        }

        foreach ($sort as $key => $comment) {
            if ($comment->parent !== null) {
                $children = $sort[$comment->parent]->getAttribute('children');
                $children[$comment->id] = $comment;
                $sort[$comment->parent]->setAttribute('children', $children);
            }
        }

        foreach ($sort as $key => $comment) {
            if ($comment->parent !== null) {
                unset($sort[$key]);
            }
        }

        return $sort;
    }

    /**
     * Show comments ordered.
     *
     * @param array $comments
     */
    public static function showCommentsOrdered($comments)
    {
        foreach ($comments as $comment) {
            /** @var Comment $comment */
            print view('themes.vortex.partials.blog.singleComment', compact('comment'));
        }
    }

    /**
     * Get gravatar or the author.
     *
     * @param $comment
     * @return String
     */
    public static function getAuthorAvatar($comment)
    {
        if ($comment->user_id !== null) {
            $avatar = getGravatar($comment->user->email);
        } else {
            $avatar = getGravatar($comment->author_email);
        }
        return $avatar;
    }

    /**
     * Get date string for humans.
     *
     * @param Carbon $date
     * @return string
     */
    public static function getDateFormatted($date)
    {
        $currentDate = Carbon::now();
        $commentDate = new Carbon($date);
        if ($currentDate->diffInYears($commentDate) == $currentDate->year || $date === null) {
            return '';
        }

        if ($currentDate->diffInSeconds($commentDate) < 1) {
            return trans('public.now');
        } else if ($currentDate->diffInSeconds($commentDate) == 1) {
            return trans('public.second_ago');
        } else if ($currentDate->diffInSeconds($commentDate) < 60) {
            return trans('public.seconds_ago', ['number' => $currentDate->diffInSeconds($commentDate)]);
        } else if ($currentDate->diffInMinutes($commentDate) == 1) {
            return trans('public.minute_ago');
        } else if ($currentDate->diffInMinutes($commentDate) < 60) {
            return trans('public.minutes_ago', ['number' => $currentDate->diffInMinutes($commentDate)]);
        } else if ($currentDate->diffInHours($commentDate) == 1) {
            return trans('public.hour_ago');
        } else if ($currentDate->diffInHours($commentDate) < 24) {
            return trans('public.hours_ago', ['number' => $currentDate->diffInHours($commentDate)]);
        } else if ($currentDate->diffInDays($commentDate) == 1) {
            return trans('public.day_ago');
        } else if ($currentDate->diffInMonths($commentDate) < 1) {
            return trans('public.days_ago', ['number' => $currentDate->diffInDays($commentDate)]);
        } else if ($currentDate->diffInMonths($commentDate) == 1) {
            return trans('public.month_ago');
        } else if ($currentDate->diffInMonths($commentDate) < 12) {
            return trans('public.months_ago', ['number' => $currentDate->diffInMonths($commentDate)]);
        } else if ($currentDate->diffInYears($commentDate) == 1) {
            return trans('public.year_ago');
        } else {
            return trans('public.years_ago', ['number' => $currentDate->diffInYears($commentDate)]);
        }
    }
}
