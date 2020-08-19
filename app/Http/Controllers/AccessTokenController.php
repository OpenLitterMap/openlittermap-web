<?php
namespace App\Http\Controllers;

use Log;
use Response;
use App\User;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use League\OAuth2\Server\Exception\OAuthServerException;
use \Laravel\Passport\Http\Controllers\AccessTokenController as ATC;

class AccessTokenController extends ATC
{
    public function issueToken(ServerRequestInterface $request)
    {
        try {
        	// \Log::info("Get parsed body");
        	\Log::info($request->getParsedBody());
            // get email as :username (default)
            $email = $request->getParsedBody()['username'];

            // get user
            $user = User::where('email', '=', $email)->first();
            \Log::info(['User', $user]);

            // generate token
            $tokenResponse = parent::issueToken($request);

            \Log::info("Token response");
            \Log::info($tokenResponse);

            // convert response to json string
            $content = $tokenResponse->getContent();
            \Log::info("Content");
            \Log::info($content);

            // convert json to array
            $data = json_decode($content, true);

            \Log::info("Data");
            \Log::info($data);

            // if (isset($data["error"])) {
            //     throw new OAuthServerException(
            //     	'The user credentials were incorrect.', 6, 'invalid_credentials', 401
            //     );
            // }

            // add access token to user
            $user = collect($user);
            $user->put('access_token', $data['access_token']);

            return Response::json(array($user));
        }
        catch (ModelNotFoundException $e) { // email notfound
            // return error message
            return response(["message" => "User not found"], 500);
        }
        catch (OAuthServerException $e) { //password not correct..token not granted
            // return error message
            return response(["message" => 
            	"The user credentials were incorrect.', 6, 'invalid_credentials"
            ], 500);
        }
        catch (Exception $e) {
            // return error message
            return response(["message" => "Internal server error!!"], 500);
        }
    }
}