<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Repositories\DoctorRepository;

class DoctorController
{
    public function __construct(private DoctorRepository $doctors) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'    => Response::json(['status' => 'success', 'data' => $this->doctors->findAll()]),
            'POST'   => $this->store($req),
            'PUT'    => $this->update($req),
            'DELETE' => $this->destroy($req),
            default  => Response::error('Method not allowed', 405),
        };
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v    = (new Validator($data))->required('name');
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $id = $this->doctors->create($data);
        Response::json(['status' => 'success', 'id' => $id], 201);
    }

    private function update(Request $req): never
    {
        $id   = (int)$req->get('id', 0);
        $data = $req->body();
        $v    = (new Validator($data))->required('name');
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $this->doctors->update($id, $data);
        Response::json(['status' => 'success']);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if (!$this->doctors->delete($id)) {
            Response::error('Doctor not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
