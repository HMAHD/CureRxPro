<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Services\QueueService;
use CureRx\Repositories\QueueRepository;

class QueueController
{
    public function __construct(
        private QueueService    $service,
        private QueueRepository $queue
    ) {}

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
        $doctorId = (int)$req->get('doctor_id', 1);
        Response::json([
            'status' => 'success',
            'data'   => $this->queue->findActive($doctorId),
        ]);
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v = (new Validator($data))
            ->required('doctor_id')
            ->required('patient_id')
            ->required('patient_name')
            ->integer('doctor_id', 1)
            ->integer('patient_id', 1);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $result = $this->service->addToQueue(
            (int)$data['doctor_id'],
            (int)$data['patient_id'],
            (string)$data['patient_name']
        );
        Response::json(['status' => 'success', 'data' => $result], 201);
    }

    private function update(Request $req): never
    {
        $id   = (int)$req->get('id', 0);
        $data = $req->body();
        $v = (new Validator($data))
            ->required('status')
            ->enum('status', ['Waiting', 'Serving', 'Done']);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        if (!$this->queue->updateStatus($id, $data['status'])) {
            Response::error('Queue entry not found', 404);
        }
        Response::json(['status' => 'success']);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if (!$this->queue->remove($id)) {
            Response::error('Queue entry not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
