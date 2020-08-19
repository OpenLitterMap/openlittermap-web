<?php

namespace App\Http\Controllers;

use Response;
use App\Http\Requests;
use Illuminate\Http\Request;

/*
* Needs a lot of work
*/ 
class ApiController extends Controller
{
    protected $statusCode = 200;

    public function getStatusCode() {
    	return $this->statusCode;
	}

	public function setStatusCode($statusCode) {
		$this->statusCode = $statusCode;
		// whenever you chain (respondNotFound), you want to return the current obj from the method
		return $this;
	}

	public function respondNotFound($message = 'Sorry! This resource is not availabile.') {
		return $this->setStatusCode(404)->respondWithError($message);
	}

	public function respondInternalError($message = 'Sorry! There has been an Internal Error.') {
		return $this->setStatusCode(500)->respondWithError($message);
	}

	public function respond($data, $headers = []) {
		return Response::json($data, $this->getStatusCode(), $headers);
	}

	// must incl Paginate method here because we are depending on its methods ->total() etc
	protected function respondWithPagination($photos, $data) {
        $data = array_merge($data, [
            'paginator' => [
                'total_count' => $photos->total(),
                'total_pages' => ceil($photos->count() / $photos->perPage()), // ceil = round up
                'current_page' => $photos->currentPage(),
                'limit' => $photos->perPage()
            ]
        ]);
        return $this->respond($data);
    }

	public function respondWithError($message) {
		return $this->respond([
			'error' => [
				'message' => $message,
				'status_code' => $this->getStatusCode()
			]
		]);
	}

	protected function respondCreated($message) {
        return $this->setStatusCode(201)->respond([
            // 'status' => 'sucess',
            'message' => $message
        ]);
    }







}
