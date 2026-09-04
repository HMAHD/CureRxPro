<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Repositories\BillingRepository;

class BillingController
{
    public function __construct(private BillingRepository $billing) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'  => $this->index($req),
            'POST' => $this->store($req),
            'PUT'  => $this->update($req),
            default => Response::error('Method not allowed', 405),
        };
    }

    private function index(Request $req): never
    {
        $doctorId = (int)$req->get('doctor_id', 1);
        $date     = $req->get('date', date('Y-m-d'));
        Response::json(['status' => 'success', 'data' => $this->billing->findByDoctor($doctorId, $date)]);
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v = (new Validator($data))
            ->required('doctor_id')
            ->required('patient_name')
            ->required('token_number')
            ->integer('doctor_id', 1)
            ->decimal('channeling_fee', 0);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $id = $this->billing->create($data);
        Response::json(['status' => 'success', 'id' => $id], 201);
    }

    private function update(Request $req): never
    {
        $id   = (int)$req->get('id', 0);
        $data = $req->body();
        $v    = (new Validator($data))->required('payment_status')->enum('payment_status', ['Paid', 'Unpaid']);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $this->billing->updatePayment($id, $data['payment_status']);
        Response::json(['status' => 'success']);
    }
}
