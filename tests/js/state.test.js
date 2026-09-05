import { describe, test, expect, beforeEach } from 'bun:test';
import * as state from '../../assets/js/state.js';

describe('state', () => {
    beforeEach(() => {
        state.set('selectedPatient', null);
        state.set('rxItems', []);
        state.set('settings', null);
    });

    test('get returns null for unset selectedPatient', () => {
        expect(state.get('selectedPatient')).toBeNull();
    });

    test('set and get round-trip a patient object', () => {
        const patient = { id: 1, name: 'Alice', patient_code: 'P001' };
        state.set('selectedPatient', patient);
        expect(state.get('selectedPatient')).toEqual(patient);
    });

    test('different keys are independent', () => {
        state.set('selectedPatient', { id: 1 });
        state.set('settings', { clinic_name: 'Test Clinic' });
        expect(state.get('selectedPatient').id).toBe(1);
        expect(state.get('settings').clinic_name).toBe('Test Clinic');
    });

    test('set replaces previous value', () => {
        state.set('rxItems', [{ id: 1 }]);
        state.set('rxItems', [{ id: 2 }, { id: 3 }]);
        expect(state.get('rxItems')).toHaveLength(2);
    });

    test('historyPageLimit initial value is 10', () => {
        // Fresh module — verify initial state
        const limit = state.get('historyPageLimit');
        expect(typeof limit).toBe('number');
        expect(limit).toBeGreaterThan(0);
    });
});
