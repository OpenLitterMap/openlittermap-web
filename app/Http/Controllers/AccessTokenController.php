<?php
namespace App\Http\Controllers;

use Log;
use Response;
use App\Models\User\User;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use League\OAuth2\Server\Exception\OAuthServerException;
use \Laravel\Passport\Http\Controllers\AccessTokenController as ATC;

class AccessTokenController extends ATC
{
    public function issueToken (ServerRequestInterface $request)
    {
        \Log::info('hit');
        try
        {
            // get email as :username (default)
            $email = $request->getParsedBody()['username'];

            // get user
            $user = User::where('email', '=', $email)->first();

            // generate token
            $tokenResponse = parent::issueToken($request);

            // convert response to json string
            $content = $tokenResponse->getContent();

            // convert json to array
            $data = json_decode($content, true);

            if (isset($data["error"])) {
                throw new OAuthServerException(
                    'The user credentials were incorrect.', 6, 'invalid_credentials', 401
                );
            }

            // add access token to user
            $user = collect($user);
            $user->put('access_token', $data['access_token']);

            return Response::json(array($user));
        }
        catch (ModelNotFoundException $e) { // email notfound
            // return error message
            \Log::error(['AccessTokenController.not_found', $e->getMessage()]);
            return response(["message" => "User not found"], 500);
        }
        catch (OAuthServerException $e) { //password not correct..token not granted
            // return error message
            \Log::error(['AccessTokenController.invalid_credentials', $e->getMessage()]);

            return response(["message" =>
            	"The user credentials were incorrect.', 6, 'invalid_credentials"
            ], 500);
        }
        catch (Exception $e) {
            // return error message
            \Log::error(['AccessTokenController.server_error', $e->getMessage()]);

            return response(["message" => "Internal server error"], 500);
        }
    }
}
