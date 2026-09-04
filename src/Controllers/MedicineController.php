<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Repositories\MedicineRepository;

class MedicineController
{
    public function __construct(private MedicineRepository $medicines) {}

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
        $limit  = max(1, min(200, (int)$req->get('limit', 50)));
        $offset = max(0, (int)$req->get('offset', 0));
        $q      = (string)$req->get('q', '');

        if ($q !== '') {
            Response::json(['status' => 'success', 'data' => $this->medicines->search($q, $limit, $offset)]);
        }
        Response::json([
            'status' => 'success',
            'data'   => $this->medicines->findAll($limit, $offset),
            'total'  => $this->medicines->count(),
        ]);
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v = (new Validator($data))
            ->required('name')
            ->decimal('unit_price', 0)
            ->integer('stock_qty', 0);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $id = $this->medicines->create($data);
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
        $this->medicines->update($id, $data);
        Response::json(['status' => 'success']);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if (!$this->medicines->delete($id)) {
            Response::error('Medicine not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
