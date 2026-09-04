// assets/js/state.js
const _state = {
    selectedPatient:    null,
    settings:           null,
    medicinesMaster:    [],
    rxItems:            [],
    templates:          [],
    queueMonitorInterval: null,
    activeMedCategory:  'all',
    historyCurrentPage: 1,
    historyPageLimit:   10,
    historyTotalPages:  1,
};

export const get = (key) => _state[key];
export const set = (key, value) => { _state[key] = value; };
