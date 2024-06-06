import { createSlice } from '@reduxjs/toolkit'

const initialState = {
    assistants: [],
    selectedAssistant: null
};


export const assistantsSlice = createSlice({
  name: 'assistants',
  initialState,
  reducers: {
    setAssistants: (state, action) => {
      state.assistants = action.payload;
    },

    setSelectedAssistant: (state, action) => {
      state.selectedAssistant = action.payload;
    },
  },
})

// Action creators are generated for each case reducer function
export const { setAssistants, setSelectedAssistant } = assistantsSlice.actions

export default assistantsSlice.reducer