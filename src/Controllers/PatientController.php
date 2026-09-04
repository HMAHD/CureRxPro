<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Repositories\PatientRepository;

class PatientController
{
    public function __construct(private PatientRepository $patients) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'    => $this->index($req),
            'POST'   => $this->store($req),
            'PUT'    => $this->update($req),
            'DELETE' => $this->destroy($req),
            default  => Response::error('Method not allowed', 405),
        };
    }

    private function index(Request $req): never
    {
        $q = $req->get('q', '');
        if ($q !== '') {
            Response::json(['status' => 'success', 'data' => $this->patients->search((string)$q)]);
        }
        $limit  = max(1, min(200, (int)($req->get('limit', 100))));
        $offset = max(0, (int)($req->get('offset', 0)));
        Response::json(['status' => 'success', 'data' => $this->patients->findAll($limit, $offset)]);
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v = (new Validator($data))
            ->required('name')
            ->integer('age', 0, 150)
            ->enum('gender', ['Male', 'Female', 'Other']);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $id = $this->patients->create($data);
        Response::json(['status' => 'success', 'id' => $id], 201);
    }

    private function update(Request $req): never
    {
        $id   = (int)$req->get('id', 0);
        $data = $req->body();
        if ($id <= 0) {
            Response::error('Missing patient id', 400);
        }
        if ($this->patients->find($id) === null) {
            Response::error('Patient not found', 404);
        }
        $v = (new Validator($data))->required('name');
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $this->patients->update($id, $data);
        Response::json(['status' => 'success']);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if ($id <= 0) {
            Response::error('Missing patient id', 400);
        }
        if (!$this->patients->softDelete($id)) {
            Response::error('Patient not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
