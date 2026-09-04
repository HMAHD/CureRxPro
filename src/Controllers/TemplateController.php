<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Repositories\TemplateRepository;

class TemplateController
{
    public function __construct(private TemplateRepository $templates) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'    => Response::json(['status' => 'success', 'data' => $this->templates->findAll()]),
            'POST'   => $this->store($req),
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
        $id = $this->templates->create($data);
        Response::json(['status' => 'success', 'id' => $id], 201);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if (!$this->templates->delete($id)) {
            Response::error('Template not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
