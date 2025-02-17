import React from 'react';
import { BrowserRouter as Router, Route, Switch } from 'react-router-dom';
import Home from './pages/Home';
import Attendance from './pages/Attendance';
import NotFound from './pages/NotFound';

function App() {
  return (
    <Router>
      <div>
        <Switch>
          <Route path="/" exact component={Home} />
          <Route path="/attendance" component={Attendance} />
          <Route component={NotFound} />
        </Switch>
      </div>
    </Router>
  );
}

export default App;