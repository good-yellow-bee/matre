<template>
  <form :action="formAction" method="POST" @submit="onSubmit">
    <input type="hidden" name="test_run[_token]" :value="csrfToken" />
    <input type="hidden" name="test_run[suite]" :value="selectedSuiteId || ''" />
    <input type="hidden" name="test_run[environment]" :value="selectedEnvironmentId || ''" />

    <div class="form-section">
      <h3 class="form-section-title">Run Configuration</h3>

      <div class="form-grid">
        <!-- Suite Dropdown -->
        <div>
          <label class="form-label" for="suite-select">Test Suite</label>
          <select
            id="suite-select"
            class="form-select"
            :disabled="loadingSuites"
            v-model="selectedSuiteId"
            @change="onSuiteChange($event.target.value ? parseInt($event.target.value, 10) : null)"
          >
            <option value="">{{ loadingSuites ? 'Loading...' : '-- Select Suite --' }}</option>
            <option v-for="suite in suites" :key="suite.id" :value="suite.id">
              {{ suite.name }} ({{ suite.typeLabel }})
            </option>
          </select>
        </div>

        <!-- Environment Dropdown -->
        <div>
          <label class="form-label" for="environment-select">Environment</label>
          <div class="position-relative">
            <select
              id="environment-select"
              class="form-select"
              :class="{ 'is-invalid': environmentState.error }"
              :disabled="environmentState.disabled"
              v-model="selectedEnvironmentId"
            >
              <option value="">
                {{ environmentState.hint || '-- Select Environment --' }}
              </option>
              <option v-for="env in environments" :key="env.id" :value="env.id">
                {{ env.name }}
              </option>
            </select>
            <div v-if="loadingEnvironments" class="spinner-overlay">
              <span class="spinner-border spinner-border-sm text-primary"></span>
            </div>
          </div>
          <div v-if="environmentState.error" class="invalid-feedback d-block">
            {{ environmentState.hint }}
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>

    <div class="form-actions">
      <button type="submit" class="btn btn-success" :disabled="!canSubmit">
        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="bi bi-play-fill"></i>
        {{ submitting ? 'Starting...' : 'Start Run' }}
      </button>
      <a :href="cancelUrl" class="btn btn-secondary">
        <i class="bi bi-x-circle"></i> Cancel
      </a>
    </div>
  </form>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTestRunForm } from '../composables/useTestRunForm';

const props = defineProps({
  suitesUrl: { type: String, required: true },
  formAction: { type: String, required: true },
  cancelUrl: { type: String, required: true },
  csrfToken: { type: String, required: true },
});

const {
  suites,
  environments,
  selectedSuiteId,
  selectedEnvironmentId,
  loadingSuites,
  loadingEnvironments,
  submitting,
  error,
  canSubmit,
  environmentState,
  fetchSuites,
  onSuiteChange,
} = useTestRunForm(props.suitesUrl);

const onSubmit = () => {
  if (!canSubmit.value) return;
  submitting.value = true;
};

onMounted(() => {
  fetchSuites();
});
</script>

<style scoped>
.form-section {
  margin-bottom: 1.5rem;
}

.form-section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.form-label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: #374151;
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.position-relative {
  position: relative;
}

.spinner-overlay {
  position: absolute;
  right: 2.5rem;
  top: 50%;
  transform: translateY(-50%);
}

.form-select:disabled {
  background-color: #f3f4f6;
  cursor: not-allowed;
}

.invalid-feedback {
  font-size: 0.875rem;
  color: #dc2626;
  margin-top: 0.25rem;
}
</style>
