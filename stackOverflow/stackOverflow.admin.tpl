<section class="mainCol col">

	<h1>Module: {$page.title}</h1>
	<hr/>
	
	<ul id="tabs">
		<li><a href="#module">Module</a></li>
	</ul>
	
	<div id="module">

		<form method="post" action="">
			
			<fieldset>
				
				<label>User id:</label>
				<input type="number" name="user_id" value="{$module.stackOverflow.settings.user_id}" required="required" placeholder="user id"/>
				<br class="clearBoth"/>
				
				<label>Limit:</label>
				<input type="number" name="stack_limit" value="{$module.stackOverflow.settings.stack_limit}" required="required" placeholder="limit"/>
				<br class="clearBoth"/>
				
				<input type="submit" name="save_stackOverflow" value="save stackOverflow module"/>
				
			</fieldset>
			
		</form>
		
	</div>
	
</section>

