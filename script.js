(() => {
  const requestForm = document.getElementById('request-form')
  if (!requestForm) {
    const navToggle = document.getElementById('nav-toggle')
    const mobileNavPanel = document.getElementById('mobile-nav-panel')

    if (navToggle && mobileNavPanel) {
      navToggle.addEventListener('click', () => {
        const isOpen = navToggle.classList.toggle('is-open')
        mobileNavPanel.classList.toggle('is-open', isOpen)
        navToggle.setAttribute('aria-expanded', String(isOpen))
      })
    }

    return
  }

  const state = {
    currentStep: 1,
    totalSteps: 4,
    isSubmitting: false,
    data: {
      name: '',
      email: '',
      organization: '',
      role: '',
      phone: '',
      city: '',
      category: '',
      title: '',
      description: '',
      urgency: 'medium',
      deadline: '',
      budget: '',
      helpType: 'volunteer',
      tags: [],
    },
    errors: {
      name: '',
      email: '',
      organization: '',
      role: '',
      phone: '',
      city: '',
      category: '',
      title: '',
      description: '',
      deadline: '',
      budget: '',
      tags: '',
      consent: '',
    },
  }

  const elements = {
    navToggle: document.getElementById('nav-toggle'),
    mobileNavPanel: document.getElementById('mobile-nav-panel'),
    stepIndicator: document.getElementById('step-indicator'),
    formSteps: Array.from(document.querySelectorAll('.form-step')),
    prevBtn: document.getElementById('prev-btn'),
    nextBtn: document.getElementById('next-btn'),
    submitBtn: document.getElementById('submit-btn'),
    prevFromSubmitBtn: document.getElementById('prev-from-submit'),
    standardNav: document.getElementById('standard-nav'),
    submitNav: document.getElementById('submit-nav'),
    descriptionLen: document.getElementById('description-len'),
    toastStack: document.getElementById('toast-stack'),
    reviewIdentity: document.getElementById('review-identity'),
    reviewNeed: document.getElementById('review-need'),
    reviewDetails: document.getElementById('review-details'),
    consent: document.getElementById('consent'),
    tagInput: document.getElementById('tag-input'),
    tagList: document.getElementById('tag-list'),
    hiddenName: document.getElementById('hidden-full-name'),
    hiddenEmail: document.getElementById('hidden-email'),
    hiddenOrganization: document.getElementById('hidden-organization'),
    hiddenRole: document.getElementById('hidden-role'),
    hiddenCategory: document.getElementById('hidden-category'),
    hiddenDescription: document.getElementById('hidden-description'),
    hiddenTitle: document.getElementById('hidden-title'),
    hiddenCity: document.getElementById('hidden-city'),
    hiddenPhone: document.getElementById('hidden-phone'),
    hiddenUrgency: document.getElementById('hidden-urgency'),
    hiddenDeadline: document.getElementById('hidden-deadline'),
    hiddenBudget: document.getElementById('hidden-budget'),
    hiddenHelpType: document.getElementById('hidden-help-type'),
    hiddenTagsText: document.getElementById('hidden-tags-text'),
    name: document.getElementById('name'),
    email: document.getElementById('email'),
    organization: document.getElementById('organization'),
    city: document.getElementById('city'),
    phone: document.getElementById('phone'),
    title: document.getElementById('title'),
    description: document.getElementById('description'),
    deadline: document.getElementById('deadline'),
    budget: document.getElementById('budget'),
  }

  const roleButtons = Array.from(document.querySelectorAll('.role-button'))
  const roleGroup = document.querySelector('.role-group')
  const categoryButtons = Array.from(document.querySelectorAll('.category-card'))
  const helpTypeRadios = Array.from(document.querySelectorAll('input[name="help_type"]'))
  const reviewEditButtons = Array.from(document.querySelectorAll('.review-edit'))

  const roleLabelMap = {
    Startup: 'Startup',
    Investor: 'Investor',
    'Service Provider': 'Service Provider',
    'Community Member': 'Community Member',
  }

  const helpTypeLabelMap = {
    volunteer: 'Volunteer Time',
    financial: 'Financial Support',
    material: 'Material/Equipment',
    other: 'Other',
  }

  const categoryTextMap = new Map(
    categoryButtons.map((button) => {
      const key = button.getAttribute('data-category') || ''
      const titleEl = button.querySelector('.category-title')
      return [key, titleEl ? titleEl.textContent || key : key]
    }),
  )

  function getErrorEl(field) {
    return document.querySelector(`[data-error-for="${field}"]`)
  }

  function setError(field, message) {
    state.errors[field] = message
    const errorEl = getErrorEl(field)
    if (errorEl) {
      errorEl.textContent = message
      if (field === 'consent') {
        errorEl.classList.toggle('is-visible', Boolean(message))
      }
    }

    const inputMap = {
      name: elements.name,
      email: elements.email,
      organization: elements.organization,
      city: elements.city,
      phone: elements.phone,
      category: document.querySelector('.category-selection'),
      title: elements.title,
      description: elements.description,
      deadline: elements.deadline,
      budget: elements.budget,
      tags: elements.tagInput,
      consent: elements.consent,
      role: document.querySelector('.role-group'),
    }

    const inputEl = inputMap[field]
    if (inputEl && typeof inputEl.setAttribute === 'function') {
      inputEl.setAttribute('aria-invalid', message ? 'true' : 'false')
    }
  }

  function clearError(field) {
    setError(field, '')
  }

  function clearAllStepErrors(fields) {
    fields.forEach((field) => clearError(field))
  }

  function markInvalidInput(input, isInvalid) {
    if (!input) return
    input.classList.toggle('is-invalid', isInvalid)
  }

  function updateErrorStyles() {
    markInvalidInput(elements.name, Boolean(state.errors.name))
    markInvalidInput(elements.email, Boolean(state.errors.email))
    markInvalidInput(elements.organization, Boolean(state.errors.organization))
    markInvalidInput(elements.city, Boolean(state.errors.city))
    markInvalidInput(elements.phone, Boolean(state.errors.phone))
    markInvalidInput(elements.title, Boolean(state.errors.title))
    markInvalidInput(elements.description, Boolean(state.errors.description))
    markInvalidInput(elements.deadline, Boolean(state.errors.deadline))
    markInvalidInput(elements.budget, Boolean(state.errors.budget))
  }

  function addToast(message, duration = 5000) {
    if (!elements.toastStack) return
    const toast = document.createElement('div')
    toast.className = 'toast'
    toast.innerHTML = `<div>${message}</div>`
    elements.toastStack.appendChild(toast)

    window.setTimeout(() => {
      toast.classList.add('toast-out')
      window.setTimeout(() => {
        toast.remove()
      }, 320)
    }, duration)
  }

  function renderStepIndicator() {
    if (!elements.stepIndicator) return
    const parts = []
    for (let i = 1; i <= state.totalSteps; i += 1) {
      const dotClass = i <= state.currentStep ? 'step-dot is-active' : 'step-dot is-inactive'
      parts.push(`<div class="${dotClass}">${i}</div>`)
      if (i < state.totalSteps) {
        const lineClass = i < state.currentStep ? 'step-line is-active' : 'step-line'
        parts.push(`<div class="${lineClass}"></div>`)
      }
    }
    elements.stepIndicator.innerHTML = parts.join('')
  }

  function updateNavVisibility() {
    if (!elements.prevBtn || !elements.nextBtn || !elements.standardNav || !elements.submitNav) return

    const isLastStep = state.currentStep === state.totalSteps
    elements.standardNav.style.display = isLastStep ? 'none' : 'flex'
    elements.submitNav.classList.toggle('is-visible', isLastStep)
    elements.prevBtn.classList.toggle('is-hidden', state.currentStep === 1)
  }

  function updateStepVisibility() {
    elements.formSteps.forEach((stepEl) => {
      const step = Number(stepEl.getAttribute('data-step'))
      const isActive = step === state.currentStep
      stepEl.classList.toggle('is-active', isActive)
      if (!isActive) {
        stepEl.style.display = 'none'
      } else {
        stepEl.style.display = 'block'
      }
    })
  }

  function formatDate(dateString) {
    if (!dateString) return '-'
    const date = new Date(dateString)
    if (Number.isNaN(date.getTime())) return '-'
    return date.toLocaleDateString('en-GB')
  }

  function renderReviewRow(label, value) {
    return `<div class="review-row"><dt>${label}</dt><dd>${value || '-'}</dd></div>`
  }

  function renderReview() {
    if (!elements.reviewIdentity || !elements.reviewNeed || !elements.reviewDetails) return

    const identityHtml = [
      renderReviewRow('Name:', escapeHtml(state.data.name)),
      renderReviewRow('Email:', escapeHtml(state.data.email)),
      state.data.organization ? renderReviewRow('Organization:', escapeHtml(state.data.organization)) : '',
      renderReviewRow('Role:', escapeHtml(roleLabelMap[state.data.role] || state.data.role || '-')),
      state.data.phone ? renderReviewRow('Phone:', escapeHtml(state.data.phone)) : '',
      renderReviewRow('City:', escapeHtml(state.data.city)),
    ].join('')

    const needHtml = [
      renderReviewRow('Category:', escapeHtml(categoryTextMap.get(state.data.category) || state.data.category || '-')),
      renderReviewRow('Title:', escapeHtml(state.data.title)),
      `<div class="review-row"><dt>Description:</dt><dd>${escapeHtml(state.data.description).replace(/\n/g, '<br>')}</dd></div>`,
    ].join('')

    const tagsHtml = state.data.tags.length > 0
      ? `<div class="review-tags">${state.data.tags.map((tag) => `<span class="review-tag">${escapeHtml(tag)}</span>`).join('')}</div>`
      : '-'

    const detailsHtml = [
      renderReviewRow('Urgency:', 'Medium - standard priority'),
      state.data.deadline ? renderReviewRow('Deadline:', escapeHtml(formatDate(state.data.deadline))) : '',
      state.data.budget ? renderReviewRow('Budget:', `${escapeHtml(state.data.budget)} EUR`) : '',
      renderReviewRow('Support Type:', escapeHtml(helpTypeLabelMap[state.data.helpType] || '-')),
      `<div class="review-row"><dt>Tags:</dt><dd>${tagsHtml}</dd></div>`,
    ].join('')

    elements.reviewIdentity.innerHTML = identityHtml
    elements.reviewNeed.innerHTML = needHtml
    elements.reviewDetails.innerHTML = detailsHtml
  }

  function renderCharCounter() {
    if (!elements.descriptionLen || !elements.description) return
    const len = elements.description.value.length
    elements.descriptionLen.textContent = String(len)
  }

  function renderRoleButtons() {
    roleButtons.forEach((button) => {
      const role = button.getAttribute('data-role') || ''
      const isActive = state.data.role === role
      button.classList.toggle('is-active', isActive)
      button.setAttribute('aria-checked', isActive ? 'true' : 'false')
    })
  }

  function selectRole(role) {
    if (!role) return
    state.data.role = role
    clearError('role')
    renderRoleButtons()
  }

  function renderCategoryButtons() {
    categoryButtons.forEach((button) => {
      const category = button.getAttribute('data-category') || ''
      button.classList.toggle('is-active', state.data.category === category)
      button.setAttribute('aria-checked', state.data.category === category ? 'true' : 'false')
    })
  }

  function renderTags() {
    if (!elements.tagList) return
    elements.tagList.innerHTML = state.data.tags
      .map((tag, index) => `<span class="tag-chip" data-tag-index="${index}">${escapeHtml(tag)}<button type="button" class="tag-remove" data-remove-tag="${index}" aria-label="Remove tag ${escapeHtml(tag)}">&times;</button></span>`)
      .join('')
  }

  function syncDataFromInputs() {
    state.data.name = elements.name ? elements.name.value.trim() : ''
    state.data.email = elements.email ? elements.email.value.trim() : ''
    state.data.organization = elements.organization ? elements.organization.value.trim() : ''
    state.data.city = elements.city ? elements.city.value.trim() : ''
    state.data.phone = elements.phone ? elements.phone.value.trim() : ''
    state.data.title = elements.title ? elements.title.value.trim() : ''
    state.data.description = elements.description ? elements.description.value : ''
    state.data.deadline = elements.deadline ? elements.deadline.value : ''
    state.data.budget = elements.budget ? elements.budget.value.trim() : ''

    state.data.urgency = 'medium'

    const helpType = helpTypeRadios.find((radio) => radio.checked)
    state.data.helpType = helpType ? helpType.value : 'volunteer'
  }

  function validateStep1() {
    syncDataFromInputs()
    clearAllStepErrors(['name', 'email', 'organization', 'city', 'phone', 'role'])

    let isValid = true

    if (!state.data.name) {
      setError('name', 'Full name is required.')
      isValid = false
    }

    if (!state.data.email) {
      setError('email', 'Email is required.')
      isValid = false
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.data.email)) {
      setError('email', 'Please enter a valid email address.')
      isValid = false
    }

    if (!state.data.role) {
      setError('role', 'Please select a role.')
      isValid = false
    }

    if (!state.data.city) {
      setError('city', 'City is required.')
      isValid = false
    } else if (state.data.city.length < 2) {
      setError('city', 'Please enter a valid city.')
      isValid = false
    }

    updateErrorStyles()
    return isValid
  }

  function validateStep2() {
    syncDataFromInputs()
    clearAllStepErrors(['category', 'title', 'description'])
    let isValid = true

    if (!state.data.category) {
      setError('category', 'Please select a category.')
      isValid = false
    }

    if (!state.data.title) {
      setError('title', 'Title is required.')
      isValid = false
    } else if (state.data.title.length < 5) {
      setError('title', 'Title must be at least 5 characters.')
      isValid = false
    }

    if (!state.data.description.trim()) {
      setError('description', 'Description is required.')
      isValid = false
    } else if (state.data.description.trim().length < 40) {
      setError('description', 'Description must be at least 40 characters.')
      isValid = false
    } else if (state.data.description.length > 2000) {
      setError('description', 'Description cannot exceed 2000 characters.')
      isValid = false
    }

    updateErrorStyles()
    return isValid
  }

  function validateStep3() {
    syncDataFromInputs()
    clearAllStepErrors(['budget', 'consent'])
    let isValid = true

    if (state.data.budget !== '') {
      const budgetNumber = Number(state.data.budget)
      if (Number.isNaN(budgetNumber) || budgetNumber < 0) {
        setError('budget', 'Budget cannot be negative.')
        isValid = false
      }
    }

    if (!elements.consent || !elements.consent.checked) {
      setError('consent', 'You must agree before continuing.')
      isValid = false
    }

    updateErrorStyles()
    return isValid
  }

  function validateCurrentStep() {
    if (state.currentStep === 1) return validateStep1()
    if (state.currentStep === 2) return validateStep2()
    if (state.currentStep === 3) return validateStep3()
    return true
  }

  function goToStep(step) {
    state.currentStep = Math.min(state.totalSteps, Math.max(1, step))
    if (state.currentStep === 4) {
      syncDataFromInputs()
      renderReview()
    }
    renderStepIndicator()
    updateStepVisibility()
    updateNavVisibility()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function handleNext() {
    if (!validateCurrentStep()) {
      addToast('Please fix highlighted fields and try again.')
      return
    }

    if (state.currentStep < state.totalSteps) {
      goToStep(state.currentStep + 1)
    }
  }

  function handlePrev() {
    if (state.currentStep > 1) {
      goToStep(state.currentStep - 1)
    }
  }

  function prepareSubmitData() {
    syncDataFromInputs()

    if (elements.hiddenName) elements.hiddenName.value = state.data.name
    if (elements.hiddenEmail) elements.hiddenEmail.value = state.data.email
    if (elements.hiddenOrganization) elements.hiddenOrganization.value = state.data.organization
    if (elements.hiddenRole) elements.hiddenRole.value = state.data.role
    if (elements.hiddenCategory) elements.hiddenCategory.value = state.data.category
    if (elements.hiddenTitle) elements.hiddenTitle.value = state.data.title
    if (elements.hiddenCity) elements.hiddenCity.value = state.data.city
    if (elements.hiddenPhone) elements.hiddenPhone.value = state.data.phone
    if (elements.hiddenUrgency) elements.hiddenUrgency.value = state.data.urgency
    if (elements.hiddenDeadline) elements.hiddenDeadline.value = state.data.deadline
    if (elements.hiddenBudget) elements.hiddenBudget.value = state.data.budget
    if (elements.hiddenHelpType) elements.hiddenHelpType.value = state.data.helpType
    if (elements.hiddenTagsText) elements.hiddenTagsText.value = state.data.tags.join(', ')
    if (elements.hiddenDescription) elements.hiddenDescription.value = state.data.description
  }

  function startSubmittingState() {
    state.isSubmitting = true
    if (elements.submitBtn) {
      elements.submitBtn.disabled = true
      elements.submitBtn.classList.add('is-loading')
      elements.submitBtn.textContent = 'Submitting...'
    }
    if (elements.prevFromSubmitBtn) {
      elements.prevFromSubmitBtn.disabled = true
    }
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }

  function setupEventListeners() {
    if (elements.navToggle && elements.mobileNavPanel) {
      elements.navToggle.addEventListener('click', () => {
        const isOpen = elements.navToggle.classList.toggle('is-open')
        elements.mobileNavPanel.classList.toggle('is-open', isOpen)
        elements.navToggle.setAttribute('aria-expanded', String(isOpen))
      })
    }

    roleButtons.forEach((button) => {
      if (button.getAttribute('type') !== 'button') {
        button.setAttribute('type', 'button')
      }

      button.addEventListener('click', () => {
        selectRole(button.getAttribute('data-role') || '')
      })

      button.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return
        event.preventDefault()
        selectRole(button.getAttribute('data-role') || '')
      })
    })

    if (roleGroup) {
      roleGroup.addEventListener('pointerup', (event) => {
        const target = event.target
        if (!(target instanceof HTMLElement)) return
        const button = target.closest('.role-button')
        if (!(button instanceof HTMLElement)) return
        selectRole(button.getAttribute('data-role') || '')
      })
    }

    categoryButtons.forEach((button) => {
      button.addEventListener('click', () => {
        state.data.category = button.getAttribute('data-category') || ''
        clearError('category')
        renderCategoryButtons()
      })
    })

    reviewEditButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const step = Number(button.getAttribute('data-edit-step'))
        goToStep(step)
      })
    })

    if (elements.description) {
      elements.description.addEventListener('input', () => {
        clearError('description')
        renderCharCounter()
      })
    }

    if (elements.title) {
      elements.title.addEventListener('input', () => clearError('title'))
    }

    ['name', 'email', 'organization', 'city', 'phone', 'deadline', 'budget'].forEach((id) => {
      const el = document.getElementById(id)
      if (el) {
        el.addEventListener('input', () => {
          clearError(id)
          updateErrorStyles()
        })
      }
    })

    if (elements.consent) {
      elements.consent.addEventListener('change', () => {
        if (elements.consent.checked) {
          clearError('consent')
        }
      })
    }

    if (elements.tagInput) {
      elements.tagInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault()
          const value = elements.tagInput.value.trim()
          if (!value) return
          if (!state.data.tags.includes(value)) {
            state.data.tags.push(value)
            clearError('tags')
            renderTags()
            renderReview()
          }
          elements.tagInput.value = ''
        }
      })
    }

    if (elements.tagList) {
      elements.tagList.addEventListener('click', (event) => {
        const target = event.target
        if (!(target instanceof HTMLElement)) return
        const idxRaw = target.getAttribute('data-remove-tag')
        if (idxRaw === null) return
        const index = Number(idxRaw)
        if (Number.isNaN(index)) return
        state.data.tags.splice(index, 1)
        renderTags()
        renderReview()
      })
    }

    if (elements.nextBtn) elements.nextBtn.addEventListener('click', handleNext)
    if (elements.prevBtn) elements.prevBtn.addEventListener('click', handlePrev)
    if (elements.prevFromSubmitBtn) elements.prevFromSubmitBtn.addEventListener('click', handlePrev)

    requestForm.addEventListener('submit', (event) => {
      if (state.isSubmitting) {
        event.preventDefault()
        return
      }

      if (state.currentStep !== 4) {
        event.preventDefault()
        goToStep(4)
        return
      }

      if (!validateStep1() || !validateStep2() || !validateStep3()) {
        event.preventDefault()
        addToast('Please fix highlighted fields and try again.')
        return
      }

      if (!state.data.role || !state.data.category) {
        event.preventDefault()
        addToast('Please select both role and category.')
        return
      }

      prepareSubmitData()
      startSubmittingState()
    })
  }

  function init() {
    renderStepIndicator()
    updateStepVisibility()
    updateNavVisibility()
    renderRoleButtons()
    renderCategoryButtons()
    renderCharCounter()
    renderTags()
    renderReview()
    setupEventListeners()
  }

  init()
})()
