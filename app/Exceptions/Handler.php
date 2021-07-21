<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
     	if ($this->shouldReport($exception) && app()->bound('sentry')) {
            app('sentry')->captureException($exception);
        }
	return parent::report($exception);
    }
 

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException){
            \Log::info('HTTPNotFound');
            \Log::info('IP: '.$request->ip());
            \Log::info('URL: '.$request->url());
            \Log::info('Method '.$request->method());
            \Log::info($request->getContent());
            
        }
        if ($e instanceof \Symfony\Component\Debug\Exception\FatalErrorException) {
            \Log::info('FatalErrorException');
            \Log::info('IP: '.$request->ip());
            \Log::info('URL: '.$request->url());
            \Log::info('Method '.$request->method());
        }
        if ($this->isHttpException($e)) {
            $url = $request->url();
            
            if(strpos($url, "doc") !== false) {
                
                $newUrl = '';
                if( strpos($url, "doc/v1") !== false ) {
                    $newUrl = '/doc/v1';
                } else if( strpos($url, "doc/v2") !== false ) {
                    $newUrl = '/doc/v2';
                } else {
                    $newUrl = '/';
                }

                if ($e->getStatusCode() == 404 || $e->getStatusCode() == 500) return redirect()->guest("$newUrl");
            }
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}
