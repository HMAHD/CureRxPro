<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Core\Validator;
use CureRx\Services\PrescriptionService;
use CureRx\Repositories\PrescriptionRepository;

class PrescriptionController
{
    public function __construct(
        private PrescriptionService    $service,
        private PrescriptionRepository $prescriptions
    ) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'    => $this->index($req),
            'POST'   => $this->store($req),
            'DELETE' => $this->destroy($req),
            default  => Response::error('Method not allowed', 405),
        };
    }

    private function index(Request $req): never
    {
        $patientId = (int)$req->get('patient_id', 0);
        if ($patientId > 0) {
            Response::json(['status' => 'success', 'data' => $this->prescriptions->findAllForPatient($patientId)]);
        }
        $id = (int)$req->get('id', 0);
        if ($id > 0) {
            $rx = $this->prescriptions->find($id);
            if (!$rx) {
                Response::error('Prescription not found', 404);
            }
            Response::json(['status' => 'success', 'data' => $rx]);
        }
        Response::error('patient_id or id required', 400);
    }

    private function store(Request $req): never
    {
        $data = $req->body();
        $v = (new Validator($data))
            ->required('patient_id')
            ->required('patient_name')
            ->integer('patient_id', 1)
            ->decimal('consultation_fee', 0);
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
        $id = $this->service->create($data);
        Response::json(['status' => 'success', 'id' => $id], 201);
    }

    private function destroy(Request $req): never
    {
        $id = (int)$req->get('id', 0);
        if (!$this->prescriptions->softDelete($id)) {
            Response::error('Prescription not found', 404);
        }
        Response::json(['status' => 'success']);
    }
}
